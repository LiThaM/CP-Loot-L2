<?php

namespace App\Console\Commands;

use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\Recipe;
use App\Contexts\Loot\Domain\Models\RecipeMaterial;
use App\Contexts\Loot\Domain\Models\RecipeOutput;
use App\Contexts\Loot\Infrastructure\Scrapers\Lu4RecipeScraper;
use App\Contexts\Loot\Infrastructure\Scrapers\Lu4Scraper;
use Illuminate\Console\Command;

class FetchLu4RecipesCommand extends Command
{
    protected $signature = 'recipes:fetch-lu4
                            {--ids= : Comma-separated list of external IDs to fetch (overrides start/end)}
                            {--start=1}
                            {--end=200000}
                            {--download-icons : Download item images}
                            {--no-download-icons : Skip image download}
                            {--skip-existing : Skip recipes already imported}
                            {--max-consecutive-missing=600 : Stop after N consecutive missing IDs}
                            {--throttle-ms=120 : Delay between requests in ms}
                            {--print : Print parsed data to console}';

    protected $description = 'Fetch recipe-scroll pages from masterwork.wiki LU4 and import their materials';

    public function handle(): int
    {
        $idsOpt = trim((string) $this->option('ids'));
        $ids = $idsOpt !== ''
            ? collect(explode(',', $idsOpt))
                ->map(fn ($v) => (int) trim($v))
                ->filter(fn ($v) => $v > 0)
                ->unique()
                ->values()
                ->all()
            : null;

        $start = (int) $this->option('start');
        $end = (int) $this->option('end');
        $download = ! $this->option('no-download-icons');
        $skip = (bool) $this->option('skip-existing');
        $print = (bool) $this->option('print');
        $maxConsecutiveMissing = (int) $this->option('max-consecutive-missing');
        $throttleMs = max(0, (int) $this->option('throttle-ms'));

        $recipeScraper = new Lu4RecipeScraper;
        $itemScraper = new Lu4Scraper;

        $total = $ids ? count($ids) : max(0, $end - $start + 1);
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $imported = 0;
        $skipped = 0;
        $missing = 0;
        $errors = 0;
        $nonRecipe = 0;
        $consecutiveMissing = 0;

        if ($ids) {
            $maxConsecutiveMissing = 0;
            foreach ($ids as $id) {
                $this->processOne(
                    id: (int) $id,
                    skip: $skip,
                    recipeScraper: $recipeScraper,
                    itemScraper: $itemScraper,
                    download: $download,
                    throttleMs: $throttleMs,
                    print: $print,
                    bar: $bar,
                    imported: $imported,
                    skipped: $skipped,
                    missing: $missing,
                    errors: $errors,
                    nonRecipe: $nonRecipe,
                    consecutiveMissing: $consecutiveMissing,
                    maxConsecutiveMissing: $maxConsecutiveMissing,
                );
            }
        } else {
            for ($id = $start; $id <= $end; $id++) {
                if ($maxConsecutiveMissing > 0 && $consecutiveMissing >= $maxConsecutiveMissing) {
                    $this->newLine(2);
                    $this->warn("Stopped: {$consecutiveMissing} consecutive IDs missing. Likely reached end of LU4 item database.");
                    break;
                }

                $this->processOne(
                    id: $id,
                    skip: $skip,
                    recipeScraper: $recipeScraper,
                    itemScraper: $itemScraper,
                    download: $download,
                    throttleMs: $throttleMs,
                    print: $print,
                    bar: $bar,
                    imported: $imported,
                    skipped: $skipped,
                    missing: $missing,
                    errors: $errors,
                    nonRecipe: $nonRecipe,
                    consecutiveMissing: $consecutiveMissing,
                    maxConsecutiveMissing: $maxConsecutiveMissing,
                );
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Imported: {$imported} | Skipped: {$skipped} | Non-recipe: {$nonRecipe} | Missing: {$missing} | Errors: {$errors}");

        return self::SUCCESS;
    }

    private function processOne(
        int $id,
        bool $skip,
        Lu4RecipeScraper $recipeScraper,
        Lu4Scraper $itemScraper,
        bool $download,
        int $throttleMs,
        bool $print,
        $bar,
        int &$imported,
        int &$skipped,
        int &$missing,
        int &$errors,
        int &$nonRecipe,
        int &$consecutiveMissing,
        int $maxConsecutiveMissing,
    ): void {
        if ($maxConsecutiveMissing > 0 && $consecutiveMissing >= $maxConsecutiveMissing) {
            $this->newLine(2);
            $this->warn("Stopped: {$consecutiveMissing} consecutive IDs missing. Likely reached end of LU4 item database.");

            return;
        }

        if ($skip) {
            $exists = Recipe::where('external_id', $id)->where('chronicle', 'LU4')->first();
            if ($exists) {
                $skipped++;
                $bar->advance();

                return;
            }
        }

        $res = $recipeScraper->fetchRecipe($id);
        if (($res['status'] ?? null) === 'missing') {
            $missing++;
            $consecutiveMissing++;
            $bar->advance();
            $this->throttle($throttleMs);

            return;
        }

        $consecutiveMissing = 0;

        if (($res['status'] ?? null) === 'error') {
            $errors++;
            $bar->advance();
            $this->throttle($throttleMs);

            return;
        }

        if (($res['status'] ?? null) === 'not_recipe') {
            $nonRecipe++;
            $bar->advance();
            $this->throttle($throttleMs);

            return;
        }

        if (($res['status'] ?? null) !== 'recipe') {
            $errors++;
            $bar->advance();
            $this->throttle($throttleMs);

            return;
        }

        $data = $res['recipe'];

        $recipeItem = $this->upsertItemPartial(
            externalId: (int) $data['external_id'],
            chronicle: 'LU4',
            fields: [
                'name' => $data['name'],
                'category' => 'Recipe',
                'source' => 'lu4',
                'icon_name' => $data['icon_name'] ?? null,
                'image_url' => $data['image_url'] ?? null,
            ],
            itemScraper: $itemScraper,
            download: $download,
        );

        $outputItem = null;
        if (! empty($data['output_external_id'])) {
            $outputExternalId = (int) $data['output_external_id'];
            $outputItem = $this->upsertItemPartial(
                externalId: $outputExternalId,
                chronicle: 'LU4',
                fields: [
                    'name' => $data['output_name'] ?? null,
                    'source' => 'lu4',
                    'icon_name' => $data['output_icon_name'] ?? null,
                    'image_url' => $data['output_image_url'] ?? null,
                ],
                itemScraper: $itemScraper,
                download: $download,
                preferFullFetch: true,
            );
        }

        $recipe = Recipe::updateOrCreate(
            ['external_id' => (int) $data['external_id'], 'chronicle' => 'LU4'],
            [
                'name' => $data['name'],
                'output_item_id' => $outputItem?->id,
                'output_quantity' => 1,
                'success_rate' => (float) ($data['success_rate'] ?? 0),
                'mp_cost' => (int) ($data['mp_cost'] ?? 0),
                'adena_fee' => (int) ($data['adena_fee'] ?? 0),
                'icon_name' => $data['icon_name'] ?? null,
                'image_url' => $recipeItem?->image_url,
                'scraper_url' => $data['scraper_url'] ?? null,
            ]
        );

        $outputItemIds = [];
        foreach (($data['outputs'] ?? []) as $out) {
            $outExternalId = (int) ($out['external_id'] ?? 0);
            if ($outExternalId <= 0) {
                continue;
            }
            $outItem = $this->upsertItemPartial(
                externalId: $outExternalId,
                chronicle: 'LU4',
                fields: [
                    'name' => $out['name'] ?? null,
                    'source' => 'lu4',
                    'icon_name' => $out['icon_name'] ?? null,
                    'image_url' => $out['image_url'] ?? null,
                ],
                itemScraper: $itemScraper,
                download: $download,
                preferFullFetch: true,
            );
            if (! $outItem) {
                continue;
            }
            $outputItemIds[] = $outItem->id;
            RecipeOutput::updateOrCreate(
                ['recipe_id' => $recipe->id, 'item_id' => $outItem->id],
                [
                    'quantity' => max(1, (int) ($out['quantity'] ?? 1)),
                    'chance' => isset($out['chance']) ? $out['chance'] : null,
                ]
            );
        }

        if (count($outputItemIds) > 0) {
            RecipeOutput::where('recipe_id', $recipe->id)
                ->whereNotIn('item_id', $outputItemIds)
                ->delete();
        }

        $materialItemIds = [];
        foreach (($data['materials'] ?? []) as $mat) {
            $matExternalId = (int) ($mat['external_id'] ?? 0);
            if ($matExternalId <= 0) {
                continue;
            }
            $matItem = $this->upsertItemPartial(
                externalId: $matExternalId,
                chronicle: 'LU4',
                fields: [
                    'name' => $mat['name'] ?? null,
                    'category' => 'Material',
                    'source' => 'lu4',
                    'icon_name' => $mat['icon_name'] ?? null,
                    'image_url' => $mat['image_url'] ?? null,
                ],
                itemScraper: $itemScraper,
                download: $download,
            );
            if (! $matItem) {
                continue;
            }
            $materialItemIds[] = $matItem->id;
            RecipeMaterial::updateOrCreate(
                ['recipe_id' => $recipe->id, 'item_id' => $matItem->id],
                ['quantity' => max(1, (int) ($mat['quantity'] ?? 1))]
            );
        }

        RecipeMaterial::where('recipe_id', $recipe->id)
            ->whereNotIn('item_id', $materialItemIds)
            ->delete();

        if ($print) {
            $this->newLine();
            $this->info("Recipe {$data['external_id']}: {$data['name']}");
            $this->line('Materials: '.count($materialItemIds).' | Success: '.($data['success_rate'] ?? 0).'%');
        }

        $imported++;
        $bar->advance();
        $this->throttle($throttleMs);
    }

    private function throttle(int $throttleMs): void
    {
        if ($throttleMs > 0) {
            usleep($throttleMs * 1000);
        }
    }

    private function upsertItemPartial(
        int $externalId,
        string $chronicle,
        array $fields,
        Lu4Scraper $itemScraper,
        bool $download,
        bool $preferFullFetch = false,
    ): ?Item {
        $existing = Item::where('external_id', $externalId)->where('chronicle', $chronicle)->first();

        if ($preferFullFetch || ! $existing || ! $existing->name || $existing->name === 'Unknown') {
            $fetched = $itemScraper->fetchItem($externalId);
            if ($fetched) {
                $fields = array_merge($fields, [
                    'name' => $fetched['name'] ?? ($fields['name'] ?? null),
                    'grade' => $fetched['grade'] ?? null,
                    'category' => $fetched['category'] ?? ($fields['category'] ?? null),
                    'icon_name' => $fetched['icon_name'] ?? ($fields['icon_name'] ?? null),
                    'image_url' => $fetched['image_url'] ?? ($fields['image_url'] ?? null),
                    'description' => $fetched['description'] ?? null,
                    'source' => 'lu4',
                ]);
            }
        }

        $payload = array_filter($fields, fn ($v) => $v !== null);

        $item = Item::updateOrCreate(
            ['external_id' => $externalId, 'chronicle' => $chronicle],
            $payload
        );

        if ($download && ! empty($item->image_url) && str_starts_with((string) $item->image_url, 'http')) {
            $local = $itemScraper->downloadIconFromUrl($item->image_url, $externalId);
            if ($local) {
                $item->image_url = $local;
                $item->save();
            }
        }

        return $item;
    }
}

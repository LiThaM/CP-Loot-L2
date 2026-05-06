<?php

namespace App\Console\Commands;

use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\Recipe;
use App\Contexts\Loot\Domain\Models\RecipeMaterial;
use App\Contexts\Loot\Domain\Models\RecipeOutput;
use App\Contexts\Loot\Infrastructure\Scrapers\L2HubScraper;
use Illuminate\Console\Command;

class FetchL2HubRecipesCommand extends Command
{
    protected $signature = 'recipes:fetch-l2hub
                            {--chronicle=IL : Chronicle code (C1, C2, C3, C4, C5, IL, CT1, GF)}
                            {--slugs= : Comma-separated recipe slugs to fetch (overrides sitemap discovery)}
                            {--skip-existing : Skip recipes already imported for this chronicle}
                            {--download-icons : Download item icons locally}
                            {--no-download-icons : Skip image download}
                            {--throttle-ms=150 : Delay between requests in ms}
                            {--print : Print parsed data to console}
                            {--limit=0 : Limit number of recipes to process (0 = no limit)}
                            {--only-100 : Only import 100%% success rate recipes}
                            {--only-60 : Only import 60%% success rate recipes}';

    protected $description = 'Fetch crafting recipes from l2hub.info for a given chronicle and import them into the database';

    private int $imported = 0;

    private int $skipped = 0;

    private int $missing = 0;

    private int $errors = 0;

    private int $notRecipe = 0;

    public function handle(): int
    {
        $chronicle = strtoupper(trim((string) $this->option('chronicle')));
        $download = ! $this->option('no-download-icons');
        $skip = (bool) $this->option('skip-existing');
        $print = (bool) $this->option('print');
        $throttleMs = max(0, (int) $this->option('throttle-ms'));
        $limit = (int) $this->option('limit');
        $only100 = (bool) $this->option('only-100');
        $only60 = (bool) $this->option('only-60');

        if (! isset(L2HubScraper::$chronicles[$chronicle])) {
            $this->error("Invalid chronicle '{$chronicle}'. Valid: " . implode(', ', array_keys(L2HubScraper::$chronicles)));

            return self::FAILURE;
        }

        $scraper = new L2HubScraper;

        // Get recipe slugs
        $slugsOpt = trim((string) $this->option('slugs'));
        if ($slugsOpt !== '') {
            $slugs = collect(explode(',', $slugsOpt))
                ->map(fn ($v) => trim($v))
                ->filter(fn ($v) => $v !== '')
                ->unique()
                ->values()
                ->all();
        } else {
            $this->info('Fetching recipe slugs from sitemap...');
            $slugs = $scraper->fetchRecipeSlugs();
            if (empty($slugs)) {
                $this->error('No recipe slugs found in sitemap.');

                return self::FAILURE;
            }
            $this->info('Found ' . count($slugs) . ' recipe slugs in sitemap.');
        }

        // Filter by success rate type
        if ($only100 && ! $only60) {
            $slugs = array_values(array_filter($slugs, fn ($s) => ! str_ends_with($s, '_i')));
            $this->info('Filtered to 100% recipes: ' . count($slugs));
        } elseif ($only60 && ! $only100) {
            $slugs = array_values(array_filter($slugs, fn ($s) => str_ends_with($s, '_i')));
            $this->info('Filtered to 60% recipes: ' . count($slugs));
        }

        if ($limit > 0) {
            $slugs = array_slice($slugs, 0, $limit);
            $this->info("Limited to {$limit} recipes.");
        }

        $this->info("=== L2Hub Recipe Importer ===");
        $this->info("Chronicle: {$chronicle} | Recipes: " . count($slugs) . " | Download icons: " . ($download ? 'Yes' : 'No'));
        $this->newLine();

        $bar = $this->output->createProgressBar(count($slugs));
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | OK: %imported% | Skip: %skipped% | Miss: %missing% | Err: %errors%');
        $bar->setMessage((string) $this->imported, 'imported');
        $bar->setMessage((string) $this->skipped, 'skipped');
        $bar->setMessage((string) $this->missing, 'missing');
        $bar->setMessage((string) $this->errors, 'errors');
        $bar->start();

        foreach ($slugs as $slug) {
            $this->processSlug($slug, $chronicle, $scraper, $skip, $download, $throttleMs, $print, $bar);
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('=== Import Complete ===');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Imported/Updated', $this->imported],
                ['Skipped (existing)', $this->skipped],
                ['Missing (404)', $this->missing],
                ['Not Recipe', $this->notRecipe],
                ['Errors', $this->errors],
                ["Total in DB ({$chronicle})", Recipe::where('chronicle', $chronicle)->count()],
            ]
        );

        return self::SUCCESS;
    }

    private function processSlug(
        string $slug,
        string $chronicle,
        L2HubScraper $scraper,
        bool $skip,
        bool $download,
        int $throttleMs,
        bool $print,
        $bar,
    ): void {
        if ($skip) {
            // Check by slug pattern: find recipe with matching scraper_url or name
            $existing = Recipe::where('chronicle', $chronicle)
                ->where('scraper_url', 'like', '%/items/' . $slug)
                ->first();
            if ($existing) {
                $this->skipped++;
                $bar->setMessage((string) $this->skipped, 'skipped');
                $bar->advance();

                return;
            }
        }

        $result = $scraper->fetchRecipe($slug, $chronicle);

        if ($result['status'] === 'missing') {
            $this->missing++;
            $bar->setMessage((string) $this->missing, 'missing');
            $bar->advance();
            $this->throttle($throttleMs);

            return;
        }

        if ($result['status'] === 'error') {
            $this->errors++;
            $bar->setMessage((string) $this->errors, 'errors');
            $bar->advance();
            $this->throttle($throttleMs);

            return;
        }

        if ($result['status'] === 'not_recipe') {
            $this->notRecipe++;
            $bar->advance();
            $this->throttle($throttleMs);

            return;
        }

        $data = $result['recipe'];

        // Upsert the recipe item (the recipe scroll itself)
        $recipeItem = $this->upsertItem(
            externalId: (int) $data['external_id'],
            chronicle: $chronicle,
            fields: [
                'name' => $data['name'],
                'grade' => $data['grade'] ?? 'NG',
                'category' => 'Recipe',
                'source' => 'l2hub',
                'icon_name' => $data['icon_name'],
            ],
            scraper: $scraper,
            download: $download,
        );

        // Upsert output item
        $outputItem = null;
        if (! empty($data['output_external_id'])) {
            $outData = $data['outputs'][0] ?? null;
            $outputItem = $this->upsertItem(
                externalId: (int) $data['output_external_id'],
                chronicle: $chronicle,
                fields: [
                    'name' => $data['output_name'],
                    'source' => 'l2hub',
                    'icon_name' => $data['output_icon_name'],
                ],
                scraper: $scraper,
                download: $download,
                itemSlug: $outData['item_name'] ?? null,
            );
        }

        // Create/update the recipe
        $recipe = Recipe::updateOrCreate(
            ['external_id' => (int) $data['external_id'], 'chronicle' => $chronicle],
            [
                'name' => $data['name'],
                'recipe_item_id' => $recipeItem?->id,
                'output_item_id' => $outputItem?->id,
                'output_quantity' => 1,
                'success_rate' => (float) $data['success_rate'],
                'mp_cost' => (int) $data['mp_cost'],
                'adena_fee' => (int) $data['adena_fee'],
                'icon_name' => $data['icon_name'],
                'image_url' => $recipeItem?->image_url,
                'scraper_url' => $data['scraper_url'],
            ]
        );

        // Upsert outputs
        $outputItemIds = [];
        foreach ($data['outputs'] as $out) {
            $outExternalId = (int) ($out['external_id'] ?? 0);
            if ($outExternalId <= 0) {
                continue;
            }
            $outItem = $this->upsertItem(
                externalId: $outExternalId,
                chronicle: $chronicle,
                fields: [
                    'name' => $out['name'],
                    'source' => 'l2hub',
                    'icon_name' => $out['icon_name'],
                ],
                scraper: $scraper,
                download: $download,
                itemSlug: $out['item_name'] ?? null,
            );
            if (! $outItem) {
                continue;
            }
            $outputItemIds[] = $outItem->id;
            RecipeOutput::updateOrCreate(
                ['recipe_id' => $recipe->id, 'item_id' => $outItem->id],
                [
                    'quantity' => max(1, (int) ($out['quantity'] ?? 1)),
                    'chance' => $out['chance'] ?? null,
                ]
            );
        }

        if (count($outputItemIds) > 0) {
            RecipeOutput::where('recipe_id', $recipe->id)
                ->whereNotIn('item_id', $outputItemIds)
                ->delete();
        }

        // Upsert materials
        $materialItemIds = [];
        foreach ($data['materials'] as $mat) {
            $matExternalId = (int) ($mat['external_id'] ?? 0);
            if ($matExternalId <= 0) {
                continue;
            }
            $matItem = $this->upsertItem(
                externalId: $matExternalId,
                chronicle: $chronicle,
                fields: [
                    'name' => $mat['name'],
                    'category' => 'Material',
                    'source' => 'l2hub',
                    'icon_name' => $mat['icon_name'],
                ],
                scraper: $scraper,
                download: $download,
                itemSlug: $mat['item_name'] ?? null,
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
            $this->info("[{$chronicle}] {$data['name']} (id:{$data['external_id']}) | {$data['success_rate']}% | Materials: " . count($materialItemIds));
        }

        $this->imported++;
        $bar->setMessage((string) $this->imported, 'imported');
        $bar->advance();
        $this->throttle($throttleMs);
    }

    private function upsertItem(
        int $externalId,
        string $chronicle,
        array $fields,
        L2HubScraper $scraper,
        bool $download,
        ?string $itemSlug = null,
    ): ?Item {
        $existing = Item::where('external_id', $externalId)
            ->where('chronicle', $chronicle)
            ->first();

        // If item exists and has a valid name, skip enrichment
        if ($existing && $existing->name && $existing->name !== 'Unknown' && $existing->name !== '-') {
            // Update fields that may have changed
            $updates = array_filter($fields, fn ($v) => $v !== null);
            if (! empty($updates)) {
                $existing->update($updates);
            }

            if ($download && $existing->icon_name && ! $this->hasLocalImage($existing)) {
                $localPath = $scraper->downloadIcon($existing->icon_name, $externalId, $chronicle);
                if ($localPath) {
                    $existing->update(['image_url' => $localPath]);
                }
            }

            return $existing;
        }

        // Enrich from item page if we have a slug and missing data
        if ($itemSlug && (! $existing || ! $existing->name || $existing->name === 'Unknown')) {
            $fetched = $scraper->fetchItem($itemSlug, $chronicle);
            if ($fetched) {
                $fields = array_merge($fields, array_filter([
                    'name' => $fetched['name'],
                    'grade' => $fetched['grade'],
                    'category' => $fetched['category'],
                    'icon_name' => $fetched['icon_name'],
                    'description' => $fetched['description'],
                    'source' => 'l2hub',
                ], fn ($v) => $v !== null));
            }
        }

        $payload = array_filter($fields, fn ($v) => $v !== null);

        $item = Item::updateOrCreate(
            ['external_id' => $externalId, 'chronicle' => $chronicle],
            $payload
        );

        if ($download && ! empty($item->icon_name) && ! $this->hasLocalImage($item)) {
            $localPath = $scraper->downloadIcon($item->icon_name, $externalId, $chronicle);
            if ($localPath) {
                $item->update(['image_url' => $localPath]);
            }
        }

        return $item;
    }

    private function hasLocalImage(Item $item): bool
    {
        return is_string($item->image_url) && str_starts_with($item->image_url, '/storage/items/');
    }

    private function throttle(int $ms): void
    {
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }
}

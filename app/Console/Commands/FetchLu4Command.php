<?php

namespace App\Console\Commands;

use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\Recipe;
use App\Contexts\Loot\Domain\Models\RecipeMaterial;
use App\Contexts\Loot\Domain\Models\RecipeOutput;
use App\Contexts\Loot\Infrastructure\Scrapers\Lu4RecipeScraper;
use App\Contexts\Loot\Infrastructure\Scrapers\Lu4Scraper;
use Illuminate\Console\Command;

class FetchLu4Command extends Command
{
    protected $signature = 'items:fetch-lu4 
                            {--start=1}
                            {--end=200000}
                            {--download-icons : Download item images}
                            {--no-download-icons : Skip image download}
                            {--skip-existing : Skip items already imported}
                            {--max-consecutive-not-found=600 : Stop after N consecutive missing IDs}
                            {--throttle-ms=80 : Delay between requests in ms}
                            {--print : Print parsed data to console}';

    protected $description = 'Fetch items from masterwork.wiki LU4 and import them';

    public function handle(): int
    {
        $start = (int) $this->option('start');
        $end = (int) $this->option('end');
        $download = ! $this->option('no-download-icons');
        $skip = $this->option('skip-existing');
        $print = $this->option('print');
        $maxConsecutiveNotFound = (int) $this->option('max-consecutive-not-found');
        $throttleMs = max(0, (int) $this->option('throttle-ms'));

        $scraper = new Lu4Scraper;
        $recipeParser = new Lu4RecipeScraper;
        $bar = $this->output->createProgressBar($end - $start + 1);
        $bar->start();

        $imported = 0;
        $recipesImported = 0;
        $skipped = 0;
        $notFound = 0;
        $consecutiveNotFound = 0;

        for ($id = $start; $id <= $end; $id++) {
            if ($maxConsecutiveNotFound > 0 && $consecutiveNotFound >= $maxConsecutiveNotFound) {
                $this->newLine(2);
                $this->warn("Stopped: {$consecutiveNotFound} consecutive IDs not found. Likely reached end of LU4 item database.");
                break;
            }

            if ($skip) {
                $exists = Item::where('external_id', $id)->where('chronicle', 'LU4')->first();
                if ($exists) {
                    $recipeExists = Recipe::where('external_id', $id)->where('chronicle', 'LU4')->first();
                    if ($recipeExists) {
                        $skipped++;
                        $bar->advance();

                        continue;
                    }

                    $looksLikeRecipe = $exists->category === 'Recipe' || str_starts_with((string) $exists->name, 'Recipe:');
                    if (! $looksLikeRecipe) {
                        $skipped++;
                        $bar->advance();

                        continue;
                    }
                }
            }

            $fetched = $scraper->fetchItemWithHtml($id);
            if (($fetched['status'] ?? null) === 'missing') {
                $notFound++;
                $consecutiveNotFound++;
                $bar->advance();
                if ($throttleMs > 0) {
                    usleep($throttleMs * 1000);
                }

                continue;
            }

            if (($fetched['status'] ?? null) !== 'ok') {
                $bar->advance();
                if ($throttleMs > 0) {
                    usleep($throttleMs * 1000);
                }

                continue;
            }

            $data = $fetched['item'];
            $html = $fetched['html'];
            $consecutiveNotFound = 0;

            $localImage = null;
            if ($download) {
                $localImage = $scraper->downloadIconFromUrl($data['image_url'], $id);
            }
            $imageUrl = $localImage ?: ($data['image_url'] ?? null);

            if ($print) {
                $this->newLine();
                $this->info("ID {$id}: {$data['name']}");
                $this->line("Type: {$data['category']} | Grade: ".($data['grade'] ?? 'N/A'));
                $this->line('Image: '.($imageUrl ?: 'N/A'));
            }

            $item = Item::updateOrCreate(
                ['external_id' => $id, 'chronicle' => 'LU4'],
                [
                    'name' => $data['name'],
                    'grade' => $this->normalizeGrade($data['grade'] ?? null, $data['name']),
                    'category' => $data['category'],
                    'source' => 'lu4',
                    'icon_name' => $data['icon_name'],
                    'image_url' => $imageUrl,
                    'description' => $data['description'] ?? null,
                ]
            );

            $recipeData = $recipeParser->parseRecipeFromHtml($html, $id);
            if ($recipeData) {
                if ($item->category !== 'Recipe') {
                    $item->update(['category' => 'Recipe']);
                }

                $outputItem = null;
                $outputExternalId = (int) ($recipeData['output_external_id'] ?? 0);
                if ($outputExternalId > 0) {
                    $outName = $recipeData['output_name'] ?? null;
                    $outIcon = $recipeData['output_icon_name'] ?? null;
                    $outImg = $recipeData['output_image_url'] ?? null;
                    $outLocal = $download ? $scraper->downloadIconFromUrl($outImg, $outputExternalId) : null;
                    $outStoredImg = $outLocal ?: $outImg;

                    $outputItem = Item::updateOrCreate(
                        ['external_id' => $outputExternalId, 'chronicle' => 'LU4'],
                        array_filter([
                            'name' => $outName,
                            'source' => 'lu4',
                            'icon_name' => $outIcon,
                            'image_url' => $outStoredImg,
                        ], fn ($v) => $v !== null && $v !== '')
                    );
                }

                $recipe = Recipe::updateOrCreate(
                    ['external_id' => $id, 'chronicle' => 'LU4'],
                    [
                        'name' => $recipeData['name'] ?? $item->name,
                        'output_item_id' => $outputItem?->id,
                        'output_quantity' => 1,
                        'success_rate' => (float) ($recipeData['success_rate'] ?? 0),
                        'mp_cost' => (int) ($recipeData['mp_cost'] ?? 0),
                        'adena_fee' => (int) ($recipeData['adena_fee'] ?? 0),
                        'icon_name' => $recipeData['icon_name'] ?? null,
                        'image_url' => $item->image_url,
                        'scraper_url' => $recipeData['scraper_url'] ?? null,
                    ]
                );

                $outputItemIds = [];
                foreach (($recipeData['outputs'] ?? []) as $out) {
                    $outExternalId = (int) ($out['external_id'] ?? 0);
                    if ($outExternalId <= 0) {
                        continue;
                    }

                    $outLocal = $download ? $scraper->downloadIconFromUrl($out['image_url'] ?? null, $outExternalId) : null;
                    $outStoredImg = $outLocal ?: ($out['image_url'] ?? null);

                    $outItem = Item::updateOrCreate(
                        ['external_id' => $outExternalId, 'chronicle' => 'LU4'],
                        array_filter([
                            'name' => $out['name'] ?? null,
                            'source' => 'lu4',
                            'icon_name' => $out['icon_name'] ?? null,
                            'image_url' => $outStoredImg,
                        ], fn ($v) => $v !== null && $v !== '')
                    );

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
                foreach (($recipeData['materials'] ?? []) as $mat) {
                    $matExternalId = (int) ($mat['external_id'] ?? 0);
                    if ($matExternalId <= 0) {
                        continue;
                    }

                    $matLocal = $download ? $scraper->downloadIconFromUrl($mat['image_url'] ?? null, $matExternalId) : null;
                    $matStoredImg = $matLocal ?: ($mat['image_url'] ?? null);

                    $matItem = Item::updateOrCreate(
                        ['external_id' => $matExternalId, 'chronicle' => 'LU4'],
                        array_filter([
                            'name' => $mat['name'] ?? null,
                            'category' => 'Material',
                            'source' => 'lu4',
                            'icon_name' => $mat['icon_name'] ?? null,
                            'image_url' => $matStoredImg,
                        ], fn ($v) => $v !== null && $v !== '')
                    );

                    $materialItemIds[] = $matItem->id;
                    RecipeMaterial::updateOrCreate(
                        ['recipe_id' => $recipe->id, 'item_id' => $matItem->id],
                        ['quantity' => max(1, (int) ($mat['quantity'] ?? 1))]
                    );
                }

                if (count($materialItemIds) > 0) {
                    RecipeMaterial::where('recipe_id', $recipe->id)
                        ->whereNotIn('item_id', $materialItemIds)
                        ->delete();
                }

                $recipesImported++;
                if ($print) {
                    $this->line('Recipe: yes');
                }
            }

            $imported++;
            $bar->advance();
            if ($throttleMs > 0) {
                usleep($throttleMs * 1000);
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Imported: {$imported} | Recipes: {$recipesImported} | Skipped: {$skipped} | Not found: {$notFound}");

        return self::SUCCESS;
    }

    private function normalizeGrade(?string $grade, string $name): string
    {
        if ($grade) {
            $g = strtoupper(trim($grade));
            $g = str_replace(' ', '', $g);
            if (in_array($g, ['S', 'A', 'B', 'C', 'D', 'E', 'NG', 'N'])) {
                return $g === 'N' ? 'NG' : $g;
            }
        }
        $s = ['Draconic', 'Imperial', 'Arcana', 'Dynasty', 'Vesper', 'Vorpal', 'Elegia', 'Icarus'];
        $a = ['Tallum', 'Majestic', 'Dark Crystal', 'Nightmare', 'Blue Wolf', 'Doom'];
        foreach ($s as $p) {
            if (stripos($name, $p) !== false) {
                return 'S';
            }
        }
        foreach ($a as $p) {
            if (stripos($name, $p) !== false) {
                return 'A';
            }
        }

        return 'NG';
    }
}

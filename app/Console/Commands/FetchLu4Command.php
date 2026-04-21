<?php

namespace App\Console\Commands;

use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\Recipe;
use App\Contexts\Loot\Infrastructure\Scrapers\Lu4RecipeScraper;
use App\Contexts\Loot\Infrastructure\Scrapers\Lu4Scraper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

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
        $invalid = 0;
        $consecutiveNotFound = 0;
        $recipeIds = [];

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

            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '' || in_array($name, ['-', '—', '–', '_'], true)) {
                $invalid++;
                $bar->advance();
                if ($throttleMs > 0) {
                    usleep($throttleMs * 1000);
                }

                continue;
            }

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
                    'name' => $name,
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
                $recipeIds[] = $id;
                $recipesImported++;
            }

            $imported++;
            $bar->advance();
            if ($throttleMs > 0) {
                usleep($throttleMs * 1000);
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Imported: {$imported} | Recipes detected: {$recipesImported} | Skipped: {$skipped} | Invalid skipped: {$invalid} | Not found: {$notFound}");

        $recipeIds = array_values(array_unique($recipeIds));
        if (count($recipeIds) > 0) {
            $this->newLine();
            $this->info('Importando recetas detectadas (recipes:fetch-lu4)...');
            Artisan::call('recipes:fetch-lu4', array_filter([
                '--ids' => implode(',', $recipeIds),
                '--skip-existing' => $skip ? true : null,
                '--throttle-ms' => $throttleMs,
                '--print' => $print ? true : null,
                '--no-download-icons' => $download ? null : true,
            ], fn ($v) => $v !== null));
            $this->output->write(Artisan::output());
        }

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

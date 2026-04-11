<?php

namespace App\Console\Commands;

use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Infrastructure\Scrapers\ElmoreScraper;
use Illuminate\Console\Command;

class FetchElmoreCommand extends Command
{
    protected $signature = 'items:fetch-elmore 
                            {--start=1 : Starting item ID}
                            {--end=10000 : Ending item ID}
                            {--chronicle=IL : Chronicle alias (c4, c5, IL)}
                            {--download-icons : Download item icons locally}
                            {--no-download-icons : Do not download item icons locally (image_url will be null)}
                            {--skip-existing : Skip items already in DB for this chronicle}';

    protected $description = 'Fetch items from ElmoreLab API and import them into the database. Iterates through item IDs sequentially.';

    private int $imported = 0;

    private int $skipped = 0;

    private int $notFound = 0;

    private int $consecutiveNotFound = 0;

    private const MAX_CONSECUTIVE_NOT_FOUND = 200;

    public function handle(): int
    {
        $start = (int) $this->option('start');
        $end = (int) $this->option('end');
        $chronicle = $this->option('chronicle');
        $downloadIcons = ! $this->option('no-download-icons');
        $skipExisting = $this->option('skip-existing');

        $validChronicles = ElmoreScraper::$chronicles;
        if (! in_array($chronicle, $validChronicles)) {
            $this->error("Invalid chronicle '{$chronicle}'. Valid options: ".implode(', ', $validChronicles));

            return self::FAILURE;
        }

        $this->info('=== ElmoreLab Item Scraper ===');
        $this->info("Chronicle: {$chronicle} | Range: {$start} - {$end} | Download icons: ".($downloadIcons ? 'Yes' : 'No'));
        $this->newLine();

        $scraper = new ElmoreScraper;
        $bar = $this->output->createProgressBar($end - $start + 1);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | Imported: %imported% | Skipped: %skipped% | NotFound: %notfound%');
        $bar->setMessage((string) $this->imported, 'imported');
        $bar->setMessage((string) $this->skipped, 'skipped');
        $bar->setMessage((string) $this->notFound, 'notfound');
        $bar->start();

        for ($id = $start; $id <= $end; $id++) {
            // Check if we should stop (too many consecutive not found = reached end of items)
            if ($this->consecutiveNotFound >= self::MAX_CONSECUTIVE_NOT_FOUND) {
                $this->newLine(2);
                $this->warn("Stopped: {$this->consecutiveNotFound} consecutive IDs not found. Likely reached end of item database.");
                break;
            }

            // Skip existing if requested (but still backfill local icons if missing)
            if ($skipExisting) {
                $existing = Item::where('external_id', $id)->where('chronicle', $chronicle)->first();
                if ($existing) {
                    $hasLocalImage = is_string($existing->image_url) && str_starts_with($existing->image_url, '/storage/items/');

                    if ($downloadIcons && ! $hasLocalImage) {
                        $hadExternalImage = is_string($existing->image_url) && str_starts_with($existing->image_url, 'http');
                        $iconName = $existing->icon_name;

                        if (! $iconName) {
                            $fresh = $scraper->fetchItem($id, $chronicle);
                            if ($fresh && $fresh['icon_name']) {
                                $existing->name = $fresh['name'];
                                $existing->grade = $scraper->guessGrade($fresh['name'], $id);
                                $existing->category = $fresh['category'];
                                $existing->source = 'elmore';
                                $existing->icon_name = $fresh['icon_name'];
                                $existing->description = $fresh['description'] ?: $fresh['additional_name'];
                                $iconName = $fresh['icon_name'];
                            }
                        }

                        if ($iconName) {
                            $localImagePath = $scraper->downloadIcon($iconName, $id, $chronicle);
                            if ($localImagePath) {
                                $existing->image_url = $localImagePath;
                                $existing->save();

                                $this->imported++;
                                $bar->setMessage((string) $this->imported, 'imported');
                                $bar->advance();
                                usleep(50000);

                                continue;
                            }
                        }

                        if ($hadExternalImage) {
                            $existing->image_url = null;
                            $existing->save();
                        }
                    }

                    $this->skipped++;
                    $bar->setMessage((string) $this->skipped, 'skipped');
                    $bar->advance();

                    continue;
                }
            }

            $itemData = $scraper->fetchItem($id, $chronicle);

            if ($itemData === null) {
                $this->notFound++;
                $this->consecutiveNotFound++;
                $bar->setMessage((string) $this->notFound, 'notfound');
                $bar->advance();

                continue;
            }

            // Reset consecutive counter since we found an item
            $this->consecutiveNotFound = 0;

            // Guess grade from name
            $grade = $scraper->guessGrade($itemData['name'], $id);

            // Download icon if requested
            $localImagePath = null;
            if ($downloadIcons && $itemData['icon_name']) {
                $localImagePath = $scraper->downloadIcon($itemData['icon_name'], $id, $chronicle);
            }

            // Upsert item
            Item::updateOrCreate(
                ['external_id' => $id, 'chronicle' => $chronicle],
                [
                    'name' => $itemData['name'],
                    'grade' => $grade,
                    'category' => $itemData['category'],
                    'source' => 'elmore',
                    'icon_name' => $itemData['icon_name'],
                    'image_url' => $localImagePath,
                    'description' => $itemData['description'] ?: $itemData['additional_name'],
                ]
            );

            $this->imported++;
            $bar->setMessage((string) $this->imported, 'imported');
            $bar->advance();

            // Throttle slightly to be respectful
            usleep(50000); // 50ms between requests
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('=== Import Complete ===');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Imported/Updated', $this->imported],
                ['Skipped (existing)', $this->skipped],
                ['Not Found', $this->notFound],
                ['Total in DB ('.$chronicle.')', Item::where('chronicle', $chronicle)->count()],
            ]
        );

        return self::SUCCESS;
    }
}

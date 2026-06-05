<?php

namespace App\Console\Commands;

use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Infrastructure\Scrapers\Lu4Scraper;
use Illuminate\Console\Command;

/**
 * Re-scrapes LU4 catalogue pages for items whose `npc_sell_price` is
 * still NULL and persists the price. Idempotent — items already
 * populated are skipped. Resumes safely after a Ctrl+C.
 *
 * Run example:
 *   php artisan items:backfill-npc-prices --throttle-ms=80
 *   php artisan items:backfill-npc-prices --limit=100 --dry-run
 */
class BackfillItemNpcSellPrice extends Command
{
    protected $signature = 'items:backfill-npc-prices
                            {--limit=0 : Stop after N items (0 = no limit)}
                            {--throttle-ms=80 : Delay between requests in ms}
                            {--dry-run : Parse and report without writing}
                            {--refresh : Re-scrape items that already have a price}';

    protected $description = 'Backfill items.npc_sell_price from masterwork.wiki LU4 pages.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $throttleMs = max(0, (int) $this->option('throttle-ms'));
        $dryRun = (bool) $this->option('dry-run');
        $refresh = (bool) $this->option('refresh');

        $scraper = new Lu4Scraper;

        $query = Item::query()
            ->where('chronicle', 'LU4')
            ->whereNotNull('external_id');

        if (! $refresh) {
            $query->whereNull('npc_sell_price');
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No items to backfill (every LU4 row already has a price). Use --refresh to force.');
            return self::SUCCESS;
        }

        if ($limit > 0 && $limit < $total) {
            $total = $limit;
        }

        $this->line("Backfilling NPC Sell Price for {$total} item(s)…");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        $missing = 0;
        $errors = 0;
        $processed = 0;

        $query->orderBy('external_id')
            ->chunkById(200, function ($chunk) use (
                $scraper,
                $throttleMs,
                $dryRun,
                $bar,
                &$updated,
                &$missing,
                &$errors,
                &$processed,
                $limit,
            ) {
                foreach ($chunk as $item) {
                    if ($limit > 0 && $processed >= $limit) {
                        return false;
                    }
                    $processed++;

                    try {
                        $res = $scraper->fetchItemWithHtml((int) $item->external_id);
                        if (($res['status'] ?? null) !== 'ok') {
                            $missing++;
                            $bar->advance();
                            if ($throttleMs > 0) usleep($throttleMs * 1000);
                            continue;
                        }
                        $price = $res['item']['npc_sell_price'] ?? null;
                        if ($price === null) {
                            $missing++;
                        } else {
                            if (! $dryRun) {
                                $item->npc_sell_price = (int) $price;
                                $item->save();
                            }
                            $updated++;
                        }
                    } catch (\Throwable $e) {
                        $errors++;
                    }

                    $bar->advance();
                    if ($throttleMs > 0) usleep($throttleMs * 1000);
                }

                return true;
            });

        $bar->finish();
        $this->newLine(2);
        $verb = $dryRun ? 'would-update' : 'updated';
        $this->info("Done. {$verb}={$updated}, missing-or-no-price={$missing}, errors={$errors}.");

        return self::SUCCESS;
    }
}

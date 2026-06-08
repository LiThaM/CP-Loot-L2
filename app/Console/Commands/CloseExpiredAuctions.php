<?php

namespace App\Console\Commands;

use App\Contexts\Party\Application\Services\AuctionService;
use App\Contexts\Party\Domain\Models\CpAuction;
use Illuminate\Console\Command;

/**
 * Cron — runs every minute via the scheduler. Closes auctions whose
 * `ends_at` has passed. The service handles the per-auction logic
 * (set winner / cancel + return stock).
 */
class CloseExpiredAuctions extends Command
{
    protected $signature = 'auctions:close';
    protected $description = 'Close auctions whose ends_at has expired.';

    public function handle(AuctionService $service): int
    {
        $expired = CpAuction::query()
            ->where('status', CpAuction::STATUS_OPEN)
            ->where('ends_at', '<=', now())
            ->get();

        if ($expired->isEmpty()) {
            return self::SUCCESS;
        }

        $closed = 0;
        $cancelled = 0;
        foreach ($expired as $auction) {
            $service->close($auction);
            $auction->refresh();
            if ($auction->status === CpAuction::STATUS_CLOSED) {
                $closed++;
            } elseif ($auction->status === CpAuction::STATUS_CANCELLED) {
                $cancelled++;
            }
        }
        $this->info("Auctions closed: {$closed}, cancelled (no bids): {$cancelled}.");
        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Contexts\Telemetry\Application\Services\DigitConsensusService;
use Illuminate\Console\Command;

class RebuildDigitConsensusCommand extends Command
{
    protected $signature = 'digits:rebuild-consensus {--bot-version= : Only rebuild for a specific bot_version pool}';
    protected $description = 'Recompute the crowdsourced digit-template consensus zip and refresh the cached artifact.';

    public function handle(DigitConsensusService $consensus): int
    {
        $version = $this->option('bot-version') ?: null;

        $this->info('Rebuilding digit consensus'.($version ? ' for version '.$version : ' (all versions)').'…');

        $result = $consensus->buildAndCache($version);

        if ($result === null) {
            $this->warn('Pool too small — no characters cleared the contributor floor. Nothing written.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Wrote %s (%d entries, %s bytes, sha256 %s).',
            $result['zip_path'],
            $result['entries'],
            number_format($result['size_bytes']),
            substr($result['sha256'], 0, 12).'…'
        ));

        foreach ($result['stats_by_char'] as $char => $stats) {
            $this->line(sprintf('  %s → %d clusters, top contributors=%d', $char, $stats['clusters'], $stats['contributors']));
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Contexts\ClientApi\Domain\Models\Release;
use Illuminate\Console\Command;

class ShowReleaseCommand extends Command
{
    protected $signature = 'releases:show {version : The release version (e.g. 0.5.4-alpha)}';
    protected $description = 'Dump the stored fields of a release row so it is easy to debug why /api/v1/version returns empty data.';

    public function handle(): int
    {
        $version = $this->argument('version');
        $release = Release::where('version', $version)->first();

        if (!$release) {
            $this->error("No release found with version={$version}");
            return self::FAILURE;
        }

        $rows = [
            ['id', $release->id],
            ['version', $release->version],
            ['channel', $release->channel],
            ['name', $release->name],
            ['published_at', $release->published_at?->toIso8601String() ?? 'NULL (draft)'],
            ['released_at', $release->released_at?->toIso8601String() ?? 'NULL'],
            ['sha256', $release->sha256 ?: 'NULL'],
            ['size_bytes', $release->size_bytes !== null ? number_format($release->size_bytes) : 'NULL'],
            ['storage_path', $release->storage_path ?: 'NULL'],
            ['critical_update', $release->critical_update ? 'true' : 'false'],
            ['min_supported_version', $release->min_supported_version ?: 'NULL'],
            ['release_notes_md', $this->summarize($release->release_notes_md)],
            ['release_notes_es', $this->summarize($release->release_notes_es)],
            ['release_notes_en', $this->summarize($release->release_notes_en)],
            ['download_count', $release->download_count],
        ];

        $this->table(['field', 'value'], $rows);

        if (!$release->release_notes_md && !$release->release_notes_es && !$release->release_notes_en) {
            $this->warn('All release_notes_* columns are NULL — /api/v1/version will return release_notes=null.');
            $this->line('  • Check the build script: it should send `release_notes` (or release_notes_es/en) in the POST /api/v1/admin/releases call.');
            $this->line('  • To backfill manually: UPDATE releases SET release_notes_md = "..." WHERE id = '.$release->id.';');
        }

        return self::SUCCESS;
    }

    private function summarize(?string $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if ($value === '') {
            return '"" (empty string)';
        }
        $preview = mb_substr($value, 0, 80);
        return sprintf('%s (%d chars)', $preview.(mb_strlen($value) > 80 ? '…' : ''), mb_strlen($value));
    }
}

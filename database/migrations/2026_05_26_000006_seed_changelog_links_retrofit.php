<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retrofit existing **web** changelog bodies so route mentions render as
 * clickable links.
 *
 * The web front-end renders bodies with `renderInlineMarkdown`, which
 * supports `[label](href)`. The desktop app has its own renderer and would
 * show the raw markdown — so this rewrite is restricted to entries whose
 * audience is `web` (the `both` and `desktop` rows are intentionally left
 * untouched). Idempotent: paths already in link form are skipped by the
 * regex.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('changelog_entries')
            ->where('audience', 'web')
            ->get(['id', 'body_es', 'body_en']);

        foreach ($rows as $row) {
            $newEs = $this->rewrite($row->body_es);
            $newEn = $this->rewrite($row->body_en);

            if ($newEs === $row->body_es && $newEn === $row->body_en) {
                continue;
            }

            DB::table('changelog_entries')
                ->where('id', $row->id)
                ->update([
                    'body_es' => $newEs,
                    'body_en' => $newEn,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // No-op: a clean revert would need a snapshot of every prior body.
        // The forward direction is non-destructive (it only adds link
        // wrappers around existing path mentions), so leaving the data as-is
        // on rollback is the safer choice.
    }

    private function rewrite(?string $body): ?string
    {
        if ($body === null || $body === '') {
            return $body;
        }

        // `/foo` or `/foo/bar` wrapped in backticks → [/foo/bar](/foo/bar).
        // The path is restricted to letters, digits, dashes, slashes and
        // underscores so we don't catch things like `/path with spaces`.
        return preg_replace_callback(
            '/`(\/[A-Za-z0-9_\-\/]+)`/',
            fn ($m) => '[' . $m[1] . '](' . $m[1] . ')',
            $body,
        );
    }
};

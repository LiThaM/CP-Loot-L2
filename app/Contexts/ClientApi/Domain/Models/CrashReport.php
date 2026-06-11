<?php

namespace App\Contexts\ClientApi\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrashReport extends Model
{
    protected $table = 'crash_reports';

    protected $fillable = [
        'anon_token_id',
        'bot_version',
        'app_version',
        'char_name',
        'os_version',
        'python_version',
        'fingerprint',
        'message',
        'stack_trace',
        'context_json',
        'occurrences',
        'reported_at',
        'last_seen_at',
        'client_ts',
    ];

    protected $casts = [
        'context_json' => 'array',
        'occurrences' => 'integer',
        'reported_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'client_ts' => 'datetime',
    ];

    public function anonToken(): BelongsTo
    {
        return $this->belongsTo(AnonToken::class, 'anon_token_id');
    }

    public static function buildFingerprint(string $stackTrace): string
    {
        // Normaliza paths variables (líneas, tmp paths, install paths) para que
        // dos instancias del mismo crash en máquinas distintas hagan match.
        $normalized = preg_replace([
            '/[A-Z]:\\\\[^\s")]+/',  // Windows paths
            '#/[^\s")]+\.py#',       // unix py paths
            '/\bline\s+\d+/i',       // ", line 42" pattern
            '/:\d+\b/',              // colon-prefixed line numbers
        ], ['<path>', '<path>', 'line <n>', ''], $stackTrace);

        return hash('sha256', (string) $normalized);
    }
}

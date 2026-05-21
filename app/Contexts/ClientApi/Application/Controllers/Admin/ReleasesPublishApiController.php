<?php

namespace App\Contexts\ClientApi\Application\Controllers\Admin;

use App\Contexts\ClientApi\Domain\Models\Release;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReleasesPublishApiController extends Controller
{
    public const MAX_BINARY_BYTES = 314_572_800; // 300 MB
    public const ALLOWED_EXTENSIONS = ['exe', 'zip'];

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'version' => ['required', 'string', 'max:50', 'regex:/^[\w.\-+]+$/'],
            'channel' => ['nullable', 'string', 'in:stable,beta'],
            'critical_update' => ['nullable', 'boolean'],
            'min_supported_version' => ['nullable', 'string', 'max:50'],
            'release_notes' => ['nullable', 'string', 'max:20000'],
            'release_notes_md' => ['nullable', 'string', 'max:20000'],
            'release_notes_es' => ['nullable', 'string', 'max:20000'],
            'release_notes_en' => ['nullable', 'string', 'max:20000'],
            'expected_sha256' => ['nullable', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/i'],
            'binary' => [
                'required',
                'file',
                'max:' . (int) (self::MAX_BINARY_BYTES / 1024),
            ],
            'publish_now' => ['nullable', 'boolean'],
        ]);

        $existing = Release::where('version', $data['version'])->first();

        $file = $request->file('binary');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return response()->json([
                'error' => 'invalid_extension',
                'message' => 'binary must be .exe or .zip (zip is used when shipping the updater alongside).',
                'received' => $ext ?: null,
            ], 422);
        }

        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false) {
            return response()->json(['error' => 'cannot_read_upload'], 500);
        }
        $sha256 = hash('sha256', $bytes);
        $size = strlen($bytes);

        if (!empty($data['expected_sha256']) && strcasecmp($data['expected_sha256'], $sha256) !== 0) {
            return response()->json([
                'error' => 'sha256_mismatch',
                'message' => 'Server-computed SHA-256 does not match expected_sha256.',
                'computed' => $sha256,
            ], 422);
        }

        $relPath = sprintf('releases/%s/AdenaLedgerStats-%s.%s', $data['version'], $data['version'], $ext);
        $disk = Storage::disk('client_blobs');
        $disk->put($relPath, $bytes);

        // `release_notes` (spec field) is treated as the canonical single-text
        // payload — it backs release_notes_md, and seeds the i18n columns when
        // the dev didn't provide localized variants.
        $unifiedNotes = $data['release_notes'] ?? $data['release_notes_md'] ?? null;

        $payload = [
            'name' => $data['name'] ?? ('AdenaLedgerStats '.$data['version']),
            'channel' => $data['channel'] ?? 'stable',
            'storage_path' => $relPath,
            'sha256' => $sha256,
            'size_bytes' => $size,
            'release_notes_md' => $unifiedNotes,
            'release_notes_es' => $data['release_notes_es'] ?? $unifiedNotes,
            'release_notes_en' => $data['release_notes_en'] ?? $unifiedNotes,
            'critical_update' => (bool) ($data['critical_update'] ?? false),
            'min_supported_version' => $data['min_supported_version'] ?? null,
            'released_at' => now(),
        ];

        if ($existing) {
            // Overwrite previous storage_path file if it changed.
            if ($existing->storage_path && $existing->storage_path !== $relPath) {
                $disk->delete($existing->storage_path);
            }
            // Preserve published_at unless publish_now is explicitly set.
            if (array_key_exists('publish_now', $data)) {
                $payload['published_at'] = $data['publish_now'] ? now() : null;
            }
            $existing->update($payload);
            $release = $existing->fresh();
            $created = false;
        } else {
            $payload['version'] = $data['version'];
            $payload['published_at'] = ($data['publish_now'] ?? false) ? now() : null;
            $release = Release::create($payload);
            $created = true;
        }

        return response()->json([
            'status' => $created ? 'created' : 'updated',
            'release_id' => $release->id,
            'version' => $release->version,
            'sha256' => $release->sha256,
            'size_bytes' => $release->size_bytes,
            'channel' => $release->channel,
            'published_at' => $release->published_at?->toIso8601String(),
            'download_url' => $release->published_at
                ? url('/api/v1/releases/'.$release->version.'/download')
                : null,
        ], $created ? 201 : 200);
    }
}

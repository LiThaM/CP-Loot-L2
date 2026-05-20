<?php

namespace App\Contexts\ClientApi\Application\Controllers\Admin;

use App\Contexts\ClientApi\Domain\Models\Release;
use App\Contexts\System\Domain\Models\ChangelogEntry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReleasesPublishApiController extends Controller
{
    public const MAX_BINARY_BYTES = 314_572_800; // 300 MB

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'version' => ['required', 'string', 'max:50', 'regex:/^[\w.\-+]+$/'],
            'channel' => ['nullable', 'string', 'in:stable,beta'],
            'critical_update' => ['nullable', 'boolean'],
            'min_supported_version' => ['nullable', 'string', 'max:50'],
            'release_notes_md' => ['nullable', 'string', 'max:20000'],
            'expected_sha256' => ['nullable', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/i'],
            'binary' => [
                'required',
                'file',
                'max:' . (int) (self::MAX_BINARY_BYTES / 1024),
            ],
            'publish_now' => ['nullable', 'boolean'],
            'create_changelog_entry' => ['nullable', 'boolean'],
        ]);

        $existing = Release::where('version', $data['version'])->first();

        $file = $request->file('binary');
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

        $relPath = sprintf('releases/%s/AdenaLedgerStats-%s.exe', $data['version'], $data['version']);
        $disk = Storage::disk('client_blobs');
        $disk->put($relPath, $bytes);

        $payload = [
            'name' => $data['name'] ?? ('AdenaLedgerStats '.$data['version']),
            'channel' => $data['channel'] ?? 'stable',
            'storage_path' => $relPath,
            'sha256' => $sha256,
            'size_bytes' => $size,
            'release_notes_md' => $data['release_notes_md'] ?? null,
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

        if (($data['create_changelog_entry'] ?? false) && !empty($data['release_notes_md'])) {
            ChangelogEntry::updateOrCreate(
                ['type' => 'release', 'version' => $data['version']],
                [
                    'audience' => 'both',
                    'release_id' => $release->id,
                    'title_es' => 'Versión '.$data['version'],
                    'title_en' => 'Release '.$data['version'],
                    'body_es' => $data['release_notes_md'],
                    'body_en' => $data['release_notes_md'],
                    'published_at' => $release->published_at ?? now()->addCentury(),
                ]
            );
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

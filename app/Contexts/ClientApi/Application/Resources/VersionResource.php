<?php

namespace App\Contexts\ClientApi\Application\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class VersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $downloadUrl = null;

        if ($this->storage_path) {
            try {
                $downloadUrl = Storage::disk('client_blobs')
                    ->temporaryUrl($this->storage_path, now()->addMinutes(5));
            } catch (\Throwable $e) {
                // local disk no soporta temporaryUrl sin configuración extra
                // F4 añade endpoint signed-route propio /api/v1/releases/{version}/download
                $downloadUrl = null;
            }
        }

        return [
            'latest_version' => $this->version,
            'channel' => $this->channel,
            'download_url' => $downloadUrl,
            'sha256' => $this->sha256,
            'size_bytes' => $this->size_bytes,
            'release_notes_url' => $this->release_notes_md
                ? url('/changelog#'.urlencode($this->version))
                : null,
            'critical_update' => (bool) $this->critical_update,
            'min_supported_version' => $this->min_supported_version,
            'released_at' => optional($this->released_at)->toIso8601String(),
        ];
    }
}

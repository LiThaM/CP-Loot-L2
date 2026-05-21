<?php

namespace App\Contexts\ClientApi\Application\Controllers;

use App\Contexts\ClientApi\Domain\Models\Release;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ReleaseDownloadController extends Controller
{
    public function redirect(string $version): Response
    {
        $release = Release::published()
            ->where('version', $version)
            ->first();

        if (!$release) {
            return response()->json(['error' => 'not_found'], 404);
        }

        if ($release->binary_purged_at !== null) {
            return response()->json([
                'error' => 'binary_no_longer_available',
                'purged_at' => $release->binary_purged_at->toIso8601String(),
            ], 410);
        }

        if (!$release->storage_path) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $disk = Storage::disk('client_blobs');

        try {
            $url = $disk->temporaryUrl($release->storage_path, now()->addMinutes(5));
            $release->increment('download_count');
            return redirect()->away($url, 302);
        } catch (\Throwable $e) {
            // Local disk fallback: signed self-route.
            $signed = URL::temporarySignedRoute(
                'api.v1.releases.serve',
                now()->addMinutes(5),
                ['version' => $release->version]
            );
            $release->increment('download_count');
            return redirect()->away($signed, 302);
        }
    }

    public function serve(Request $request, string $version): Response
    {
        if (!$request->hasValidSignature()) {
            return response()->json(['error' => 'invalid_signature'], 403);
        }

        $release = Release::published()->where('version', $version)->first();
        if (!$release) {
            return response()->json(['error' => 'not_found'], 404);
        }

        if ($release->binary_purged_at !== null) {
            return response()->json([
                'error' => 'binary_no_longer_available',
                'purged_at' => $release->binary_purged_at->toIso8601String(),
            ], 410);
        }

        if (!$release->storage_path) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $disk = Storage::disk('client_blobs');
        if (!$disk->exists($release->storage_path)) {
            return response()->json(['error' => 'file_missing'], 404);
        }

        $ext = strtolower(pathinfo($release->storage_path, PATHINFO_EXTENSION)) ?: 'exe';

        return response($disk->get($release->storage_path), 200, [
            'Content-Type' => $ext === 'zip' ? 'application/zip' : 'application/octet-stream',
            'Content-Disposition' => sprintf(
                'attachment; filename="AdenaLedgerStats-%s.%s"',
                $release->version,
                $ext
            ),
        ]);
    }
}

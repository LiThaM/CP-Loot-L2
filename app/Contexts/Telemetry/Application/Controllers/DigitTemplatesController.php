<?php

namespace App\Contexts\Telemetry\Application\Controllers;

use App\Contexts\ClientApi\Domain\Models\AnonToken;
use App\Contexts\Telemetry\Application\Requests\UploadDigitTemplatesRequest;
use App\Contexts\Telemetry\Application\Services\DigitConsensusService;
use App\Contexts\Telemetry\Application\Services\ZipValidatorService;
use App\Contexts\Telemetry\Domain\Models\DigitTemplateSubmission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DigitTemplatesController extends Controller
{
    private const DIM_MIN_W = 16;
    private const DIM_MAX_W = 24;
    private const DIM_MIN_H = 24;
    private const DIM_MAX_H = 40;

    public function __construct(
        private readonly ZipValidatorService $zipValidator,
        private readonly DigitConsensusService $consensus,
    ) {}

    public function store(UploadDigitTemplatesRequest $request): JsonResponse
    {
        /** @var AnonToken $anon */
        $anon = $request->attributes->get('anon_token');

        $existing = DigitTemplateSubmission::where('anon_token_id', $anon->id)->count();
        if ($existing >= UploadDigitTemplatesRequest::PER_ANON_TOKEN_QUOTA) {
            return response()->json([
                'error' => 'quota_exceeded',
                'message' => sprintf(
                    'Per-anon-token quota of %d submissions reached.',
                    UploadDigitTemplatesRequest::PER_ANON_TOKEN_QUOTA
                ),
            ], 429);
        }

        $remaining = UploadDigitTemplatesRequest::PER_ANON_TOKEN_QUOTA - $existing;
        $botVersion = $request->input('bot_version');

        $zipPath = $request->file('templates')->getRealPath();
        $disk = Storage::disk('client_blobs');
        $tokenHashShort = substr($anon->hashedToken(), 0, 8);
        $monthBucket = now()->format('Y-m');

        $stored = [];

        try {
            $result = $this->zipValidator->iterate(
                $zipPath,
                UploadDigitTemplatesRequest::MAX_PNG_ENTRIES,
                UploadDigitTemplatesRequest::MAX_UNCOMPRESSED_BYTES,
                function (string $name, int $uncompressedBytes, callable $reader) use (
                    $disk, $tokenHashShort, $monthBucket, $anon, $botVersion, &$stored, &$remaining
                ) {
                    if ($remaining <= 0) {
                        return 'per-anon-token quota reached mid-zip';
                    }

                    if (!str_ends_with(strtolower($name), '.png')) {
                        return 'not a .png file';
                    }

                    $base = basename($name);
                    if (!preg_match('/^([0-9]|slash)_\d+\.png$/i', $base, $m)) {
                        return 'filename does not match <char>_<idx>.png';
                    }
                    $char = strtolower($m[1]);

                    $bytes = $reader();
                    if ($bytes === null || strlen($bytes) === 0) {
                        return 'cannot read entry';
                    }

                    [$w, $h, $isPng] = $this->inspectPng($bytes);
                    if (!$isPng) {
                        return 'not a valid PNG';
                    }
                    if ($w < self::DIM_MIN_W || $w > self::DIM_MAX_W
                        || $h < self::DIM_MIN_H || $h > self::DIM_MAX_H) {
                        return sprintf('dimensions %dx%d out of [%d-%d]x[%d-%d]', $w, $h, self::DIM_MIN_W, self::DIM_MAX_W, self::DIM_MIN_H, self::DIM_MAX_H);
                    }

                    $phash = $this->consensus->perceptualHash($bytes);
                    $sharpness = $this->consensus->sharpness($bytes);

                    $relPath = sprintf(
                        'templates/digits/%s/%s/%s.png',
                        $monthBucket,
                        $tokenHashShort,
                        Str::uuid()->toString()
                    );

                    if (!$disk->put($relPath, $bytes)) {
                        return 'storage failed';
                    }

                    DigitTemplateSubmission::create([
                        'anon_token_id' => $anon->id,
                        'char' => $char,
                        'storage_path' => $relPath,
                        'phash' => $phash,
                        'bot_version' => $botVersion,
                        'sharpness' => $sharpness,
                        'dim_w' => $w,
                        'dim_h' => $h,
                        'original_size_bytes' => $uncompressedBytes,
                        'kept_for_training' => false,
                        'submitted_at' => now(),
                    ]);

                    $stored[] = $relPath;
                    $remaining--;

                    return true;
                }
            );
        } catch (RuntimeException $e) {
            foreach ($stored as $path) {
                $disk->delete($path);
            }

            return response()->json([
                'error' => 'zip_invalid',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'accepted' => $result['accepted'],
            'rejected' => $result['rejected'],
            'reasons' => $result['reasons'],
        ], 201);
    }

    /**
     * Serve the consensus zip. Uses the cached artifact written by the
     * RebuildDigitConsensusCommand cron when present; otherwise builds it
     * on demand (first hit warms the cache).
     */
    public function index(Request $request): SymfonyResponse
    {
        $botVersion = $request->query('version');
        if ($botVersion !== null && !preg_match('/^[\w.\-+]+$/', $botVersion)) {
            return response()->json(['error' => 'invalid_version'], 422);
        }

        $disk = Storage::disk('client_blobs');
        $cachedPath = $this->consensus->cachedZipPath($botVersion);

        $zipBytes = null;
        if ($disk->exists($cachedPath)) {
            $zipBytes = $disk->get($cachedPath);
        } else {
            $built = $this->consensus->buildAndCache($botVersion);
            if ($built === null) {
                return response()->noContent(204);
            }
            $zipBytes = $disk->get($built['zip_path']);
        }

        if ($zipBytes === null || $zipBytes === '') {
            return response()->noContent(204);
        }

        $etag = '"'.hash('sha256', $zipBytes).'"';
        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304, ['ETag' => $etag]);
        }

        return response($zipBytes, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => sprintf('attachment; filename="digit-consensus%s.zip"', $botVersion ? '-'.$botVersion : ''),
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $version = $request->query('version');

        $base = DigitTemplateSubmission::query();
        if ($version && preg_match('/^[\w.\-+]+$/', $version)) {
            $base->where('bot_version', $version);
        }

        $total = (clone $base)->count();
        $contributors = (clone $base)->distinct('anon_token_id')->count('anon_token_id');

        $byChar = (clone $base)
            ->selectRaw('char, COUNT(*) as c')
            ->groupBy('char')
            ->pluck('c', 'char')
            ->toArray();

        $disk = Storage::disk('client_blobs');
        $cachedPath = $this->consensus->cachedZipPath($version ?: null);
        $consensusReady = $disk->exists($cachedPath);

        $lastRebuild = null;
        if ($consensusReady) {
            try {
                $lastRebuild = date('c', $disk->lastModified($cachedPath));
            } catch (\Throwable $e) {
                $lastRebuild = null;
            }
        }

        return response()->json([
            'total_templates' => (int) $total,
            'by_char' => $byChar,
            'contributors' => (int) $contributors,
            'consensus_ready' => $consensusReady,
            'last_consensus_rebuild' => $lastRebuild,
            'version_filter' => $version ?: null,
        ]);
    }

    /**
     * @return array{0:int,1:int,2:bool} [width, height, isPng]
     */
    private function inspectPng(string $bytes): array
    {
        if (strlen($bytes) < 24) {
            return [0, 0, false];
        }
        if (substr($bytes, 0, 8) !== "\x89PNG\r\n\x1A\n") {
            return [0, 0, false];
        }
        $width = unpack('Nw', substr($bytes, 16, 4))['w'] ?? 0;
        $height = unpack('Nh', substr($bytes, 20, 4))['h'] ?? 0;
        return [$width, $height, true];
    }
}

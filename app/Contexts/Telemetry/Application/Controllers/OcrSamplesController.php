<?php

namespace App\Contexts\Telemetry\Application\Controllers;

use App\Contexts\ClientApi\Domain\Models\AnonToken;
use App\Contexts\Telemetry\Application\Requests\SubmitOcrSampleRequest;
use App\Contexts\Telemetry\Domain\Models\OcrSample;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OcrSamplesController extends Controller
{
    public function store(SubmitOcrSampleRequest $request): JsonResponse
    {
        /** @var AnonToken $anon */
        $anon = $request->attributes->get('anon_token');

        $bytes = file_get_contents($request->file('image')->getRealPath());
        if ($bytes === false || $bytes === '') {
            return response()->json(['error' => 'cannot_read_image'], 422);
        }

        // Magic bytes check — clients may try to disguise other formats.
        if (substr($bytes, 0, 8) !== "\x89PNG\r\n\x1A\n") {
            return response()->json(['error' => 'invalid_png'], 422);
        }

        $hash = hash('sha256', $bytes);
        $existing = OcrSample::where('image_hash_sha256', $hash)->first();
        if ($existing) {
            return response()->json([
                'error' => 'duplicate',
                'sample_id' => $existing->id,
                'image_hash' => $hash,
            ], 409);
        }

        $tokenHashShort = substr($anon->hashedToken(), 0, 8);
        $relPath = sprintf('ocr/samples/%s/%s.png', $tokenHashShort, $hash);

        $disk = Storage::disk('client_blobs');
        if (!$disk->put($relPath, $bytes)) {
            return response()->json(['error' => 'storage_failed'], 500);
        }

        // Prefer ocr_text (new) but fall back to ground_truth (legacy).
        $ocrText = $request->input('ocr_text') ?? $request->input('ground_truth');

        $sample = OcrSample::create([
            'anon_token_id' => $anon->id,
            'category' => $request->input('category'),
            'storage_path' => $relPath,
            'image_hash_sha256' => $hash,
            'ground_truth' => $request->input('ground_truth') ?? $ocrText,
            'expected_value' => $request->input('expected_value'),
            'actual_ocr' => $request->input('actual_ocr') ?? $ocrText,
            'confidence' => $request->input('confidence'),
            'bot_version' => $request->input('bot_version'),
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => 'created',
            'sample_id' => $sample->id,
            'image_hash' => $hash,
        ], 201);
    }

    public function stats(Request $request): JsonResponse
    {
        $total = OcrSample::count();
        $byCategory = OcrSample::query()
            ->selectRaw('category, COUNT(*) as c')
            ->groupBy('category')
            ->pluck('c', 'category')
            ->toArray();

        $contributors = OcrSample::query()
            ->distinct('anon_token_id')
            ->count('anon_token_id');

        $labeled = OcrSample::where('status', 'labeled')->count();

        // your_contribution requires an anon token resolved by the middleware;
        // this route is open (no middleware) so we accept it as a header for
        // best-effort personalisation only.
        $yourContribution = null;
        $anonId = $this->resolveAnonTokenId($request);
        if ($anonId !== null) {
            $yourContribution = OcrSample::where('anon_token_id', $anonId)->count();
        }

        $lastExport = OcrSample::query()
            ->whereNotNull('reviewed_at')
            ->max('reviewed_at');

        return response()->json([
            'total_samples' => (int) $total,
            'by_category' => $byCategory,
            'contributors' => (int) $contributors,
            'labeled_samples' => (int) $labeled,
            'your_contribution' => $yourContribution,
            'last_export' => $lastExport ? (string) $lastExport : null,
        ]);
    }

    /**
     * Best-effort anon_token resolver for the public stats endpoint. Returns
     * the id of the AnonToken matching the X-Anon-Token header, or null when
     * the header is missing/invalid (we don't reject — stats stays public).
     */
    private function resolveAnonTokenId(Request $request): ?int
    {
        $raw = $request->headers->get('X-Anon-Token');
        if (!$raw || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $raw)) {
            return null;
        }
        $token = \App\Contexts\ClientApi\Domain\Models\AnonToken::where('token_uuid', strtolower($raw))->first();
        return $token?->id;
    }
}

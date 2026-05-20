<?php

namespace App\Contexts\Telemetry\Application\Controllers;

use App\Contexts\ClientApi\Domain\Models\AnonToken;
use App\Contexts\Telemetry\Application\Requests\SubmitOcrSampleRequest;
use App\Contexts\Telemetry\Domain\Models\OcrSample;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
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

        $hash = hash('sha256', $bytes);
        $existing = OcrSample::where('image_hash_sha256', $hash)->first();
        if ($existing) {
            return response()->json([
                'status' => 'duplicate',
                'sample_id' => $existing->id,
            ], 200);
        }

        $tokenHashShort = substr($anon->hashedToken(), 0, 8);
        $relPath = sprintf('ocr/samples/%s/%s.png', $tokenHashShort, $hash);

        $disk = Storage::disk('client_blobs');
        if (!$disk->put($relPath, $bytes)) {
            return response()->json(['error' => 'storage_failed'], 500);
        }

        $sample = OcrSample::create([
            'anon_token_id' => $anon->id,
            'category' => $request->input('category'),
            'storage_path' => $relPath,
            'image_hash_sha256' => $hash,
            'ground_truth' => $request->input('ground_truth'),
            'expected_value' => $request->input('expected_value'),
            'actual_ocr' => $request->input('actual_ocr'),
            'confidence' => $request->input('confidence'),
        ]);

        return response()->json([
            'status' => 'accepted',
            'sample_id' => $sample->id,
        ], 201);
    }
}

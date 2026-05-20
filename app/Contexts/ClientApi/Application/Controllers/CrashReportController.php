<?php

namespace App\Contexts\ClientApi\Application\Controllers;

use App\Contexts\ClientApi\Application\Requests\SubmitCrashRequest;
use App\Contexts\ClientApi\Domain\Models\AnonToken;
use App\Contexts\ClientApi\Domain\Models\CrashReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CrashReportController extends Controller
{
    public function store(SubmitCrashRequest $request): JsonResponse
    {
        /** @var AnonToken|null $anon */
        $anon = $request->attributes->get('anon_token');

        $fingerprint = CrashReport::buildFingerprint($request->input('stack_trace'));

        $report = CrashReport::create([
            'anon_token_id' => $anon?->id,
            'bot_version' => $request->input('bot_version'),
            'os_version' => $request->input('os_version'),
            'python_version' => $request->input('python_version'),
            'fingerprint' => $fingerprint,
            'message' => $request->input('message'),
            'stack_trace' => $request->input('stack_trace'),
            'context_json' => $request->input('context'),
            'reported_at' => now(),
        ]);

        return response()->json([
            'status' => 'accepted',
            'crash_id' => $report->id,
            'fingerprint' => $fingerprint,
        ], 201);
    }
}

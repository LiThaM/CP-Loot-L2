<?php

namespace App\Contexts\ClientApi\Application\Controllers;

use App\Contexts\ClientApi\Domain\Models\AnonToken;
use App\Contexts\Telemetry\Domain\Models\DigitTemplateSubmission;
use App\Contexts\Telemetry\Domain\Models\ItemLu4UnknownReport;
use App\Contexts\Telemetry\Domain\Models\OcrSample;
use App\Contexts\Telemetry\Domain\Models\TelemetrySession;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class MeDataController extends Controller
{
    public function destroy(Request $request): Response
    {
        /** @var AnonToken|null $anon */
        $anon = $request->attributes->get('anon_token');
        if (!$anon) {
            return response()->json(['error' => 'missing_anon_token'], 400);
        }

        $disk = Storage::disk('client_blobs');

        $paths = array_merge(
            DigitTemplateSubmission::where('anon_token_id', $anon->id)
                ->pluck('storage_path')->all(),
            OcrSample::where('anon_token_id', $anon->id)
                ->pluck('storage_path')->all(),
            SupportTicket::where('anon_token_id', $anon->id)
                ->whereNotNull('bot_context_path')
                ->pluck('bot_context_path')->all(),
        );

        foreach ($paths as $p) {
            try {
                $disk->delete($p);
            } catch (\Throwable $e) {
                // intentar continuar; el GDPR cascade es best-effort en disk
            }
        }

        DB::transaction(function () use ($anon) {
            DigitTemplateSubmission::where('anon_token_id', $anon->id)->delete();
            OcrSample::where('anon_token_id', $anon->id)->delete();
            TelemetrySession::where('anon_token_id', $anon->id)->delete();
            ItemLu4UnknownReport::where('anon_token_id', $anon->id)->delete();

            // Tickets se anonimizan (no se borran, mantenemos historial soporte)
            SupportTicket::where('anon_token_id', $anon->id)->update([
                'anon_token_id' => null,
                'bot_context_path' => null,
                'email' => null,
            ]);

            $anon->delete();
        });

        return response()->noContent();
    }
}

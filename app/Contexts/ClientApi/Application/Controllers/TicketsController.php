<?php

namespace App\Contexts\ClientApi\Application\Controllers;

use App\Contexts\ClientApi\Application\Requests\CreateBotTicketRequest;
use App\Contexts\ClientApi\Domain\Models\AnonToken;
use App\Contexts\Telemetry\Application\Services\ZipValidatorService;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class TicketsController extends Controller
{
    public function __construct(private readonly ZipValidatorService $zipValidator) {}

    public function store(CreateBotTicketRequest $request): JsonResponse
    {
        /** @var AnonToken $anon */
        $anon = $request->attributes->get('anon_token');

        $botContextPath = null;
        if ($request->hasFile('bot_context')) {
            try {
                $botContextPath = $this->storeBotContext($request, $anon);
            } catch (RuntimeException $e) {
                return response()->json([
                    'error' => 'invalid_bot_context',
                    'message' => $e->getMessage(),
                ], 422);
            }
        }

        $trackingToken = Str::random(40);

        // user_id se asocia si el request lleva Bearer Sanctum token. Auth opcional.
        $userId = optional($request->user())->id;

        $ticket = SupportTicket::create([
            'user_id' => $userId,
            'subject' => $request->input('subject'),
            'message' => $request->input('message'),
            'name' => null,
            'email' => $request->input('email'),
            'status' => 'open',
            'type' => 'support',
            'source' => 'bot_app',
            'anon_token_id' => $anon->id,
            'tracking_token' => $trackingToken,
            'bot_context_path' => $botContextPath,
            'ticket_number' => SupportTicket::generateTicketNumber(),
            'metadata' => [
                'category' => $request->input('category'),
                'submitted_via' => 'api/v1',
            ],
        ]);

        return response()->json([
            'status' => 'created',
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'tracking_token' => $trackingToken,
            'tracking_url' => url('/t/'.$trackingToken),
        ], 201);
    }

    public function showByTrackingToken(string $token): JsonResponse
    {
        $ticket = SupportTicket::where('tracking_token', $token)->first();

        if (!$ticket) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $replies = $ticket->replies()
            ->orderBy('created_at')
            ->get(['id', 'message', 'created_at']);

        return response()->json([
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'category' => data_get($ticket->metadata, 'category'),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'closed_at' => $ticket->closed_at?->toIso8601String(),
            'replies' => $replies->map(fn ($r) => [
                'id' => $r->id,
                'message' => $r->message,
                'created_at' => $r->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    private function storeBotContext(Request $request, AnonToken $anon): string
    {
        $file = $request->file('bot_context');
        $localPath = $file->getRealPath();

        $result = $this->zipValidator->iterate(
            $localPath,
            50,
            8_388_608, // 8 MB descomprimido
            function (string $name, int $bytes, callable $reader) {
                $allowedEntries = ['log_tail.txt', 'settings.json', 'session_stats.json'];
                if (!in_array(basename($name), $allowedEntries, true)) {
                    return 'entry name not allowed for bot_context';
                }
                $payload = $reader();
                return is_string($payload) ? true : 'cannot read';
            }
        );

        if ($result['accepted'] === 0) {
            throw new RuntimeException('bot_context ZIP contained no allowed entries.');
        }

        $disk = Storage::disk('client_blobs');
        $tokenHashShort = substr($anon->hashedToken(), 0, 8);
        $relPath = sprintf(
            'support/bot_context/%s/%s.zip',
            $tokenHashShort,
            Str::uuid()->toString()
        );

        $bytes = file_get_contents($localPath);
        if ($bytes === false || !$disk->put($relPath, $bytes)) {
            throw new RuntimeException('Failed to persist bot_context ZIP.');
        }

        return $relPath;
    }
}

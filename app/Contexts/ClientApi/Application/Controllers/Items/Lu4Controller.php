<?php

namespace App\Contexts\ClientApi\Application\Controllers\Items;

use App\Contexts\ClientApi\Application\Resources\Lu4ItemResource;
use App\Contexts\ClientApi\Domain\Models\AnonToken;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Telemetry\Domain\Models\ItemLu4UnknownReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class Lu4Controller extends Controller
{
    public function index(Request $request)
    {
        $maxUpdatedAt = Item::lu4()->max('updated_at');
        $etag = '"'.($maxUpdatedAt ? md5((string) $maxUpdatedAt) : 'empty').'"';

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        $perPage = min((int) $request->query('limit', 500), 1000);

        $items = Item::lu4()
            ->orderBy('id')
            ->cursorPaginate($perPage);

        return Lu4ItemResource::collection($items)
            ->additional(['etag' => trim($etag, '"')])
            ->response()
            ->header('ETag', $etag);
    }

    public function reportUnknown(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ocr_context' => ['nullable', 'string', 'max:500'],
            'count_seen' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        /** @var AnonToken $anon */
        $anon = $request->attributes->get('anon_token');

        $existing = ItemLu4UnknownReport::where('name', $data['name'])
            ->where('anon_token_id', $anon->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            $existing->increment('count_seen', (int) ($data['count_seen'] ?? 1));
            return response()->json([
                'status' => 'updated',
                'report_id' => $existing->id,
                'count_seen' => $existing->count_seen,
            ], 200);
        }

        $report = ItemLu4UnknownReport::create([
            'name' => $data['name'],
            'anon_token_id' => $anon->id,
            'ocr_context' => $data['ocr_context'] ?? null,
            'count_seen' => $data['count_seen'] ?? 1,
            'status' => 'pending',
            'reported_at' => now(),
        ]);

        return response()->json([
            'status' => 'accepted',
            'report_id' => $report->id,
        ], 201);
    }
}

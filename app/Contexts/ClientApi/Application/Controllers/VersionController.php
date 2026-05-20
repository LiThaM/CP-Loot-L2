<?php

namespace App\Contexts\ClientApi\Application\Controllers;

use App\Contexts\ClientApi\Application\Resources\VersionResource;
use App\Contexts\ClientApi\Domain\Models\Release;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VersionController extends Controller
{
    public function latest(Request $request): JsonResource
    {
        $channel = $request->query('channel', 'stable');
        if (!in_array($channel, ['stable', 'beta'], true)) {
            $channel = 'stable';
        }

        $release = Release::published()
            ->channel($channel)
            ->orderByDesc('released_at')
            ->firstOrFail();

        return new VersionResource($release);
    }
}

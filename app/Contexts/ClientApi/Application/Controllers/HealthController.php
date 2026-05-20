<?php

namespace App\Contexts\ClientApi\Application\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthController extends Controller
{
    public function show(): JsonResponse
    {
        $db = $this->checkDatabase();
        $storage = $this->checkStorage();

        $status = ($db === 'ok' && $storage === 'ok') ? 'ok' : 'degraded';

        return response()->json([
            'status' => $status,
            'db' => $db,
            'storage' => $storage,
            'version' => 'v1',
        ], $status === 'ok' ? 200 : 503);
    }

    private function checkDatabase(): string
    {
        try {
            DB::connection()->select('SELECT 1');
            return 'ok';
        } catch (Throwable $e) {
            return 'error';
        }
    }

    private function checkStorage(): string
    {
        try {
            $disk = Storage::disk('client_blobs');
            $disk->put('.healthcheck', (string) now()->timestamp);
            return $disk->exists('.healthcheck') ? 'ok' : 'error';
        } catch (Throwable $e) {
            return 'error';
        }
    }
}

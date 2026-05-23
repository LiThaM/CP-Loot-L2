<?php

use App\Contexts\ClientApi\Application\Controllers\Admin\ReleasesPublishApiController;
use App\Contexts\ClientApi\Application\Controllers\ChangelogController;
use App\Contexts\ClientApi\Application\Controllers\CrashReportController;
use App\Contexts\ClientApi\Application\Controllers\HealthController;
use App\Contexts\ClientApi\Application\Controllers\Items\Lu4Controller;
use App\Contexts\ClientApi\Application\Controllers\MeDataController;
use App\Contexts\ClientApi\Application\Controllers\ReleaseDownloadController;
use App\Contexts\ClientApi\Application\Controllers\ReleasesListController;
use App\Contexts\ClientApi\Application\Controllers\TicketsController;
use App\Contexts\ClientApi\Application\Controllers\VersionController;
use App\Contexts\Telemetry\Application\Controllers\DigitTemplatesController;
use App\Contexts\Telemetry\Application\Controllers\OcrSamplesController;
use App\Contexts\Telemetry\Application\Controllers\TelemetrySessionController;
use Illuminate\Support\Facades\Route;

/*
| API v1 — consumida por el cliente desktop AdenaLedgerStats (Lu4Bot).
| Stateless. Sin CSRF. Sin sesiones. Sin Inertia.
| Prefijo /api/v1 aplicado en bootstrap/app.php (apiPrefix).
*/

Route::middleware(['api.log'])->group(function () {

    // Públicos (sin client_key) — health, version, futuro download de releases.
    Route::get('/health', [HealthController::class, 'show'])
        ->name('api.v1.health');

    Route::get('/version', [VersionController::class, 'latest'])
        ->middleware('throttle:api-v1')
        ->name('api.v1.version');

    Route::get('/releases/latest', [VersionController::class, 'latest'])
        ->middleware('throttle:api-v1')
        ->name('api.v1.releases.latest');

    // Listado histórico — la web puede pintar el changelog completo aunque
    // los binarios viejos hayan sido purgados. ?include_purged=true para
    // incluir las filas cuyo .zip ya no está en storage.
    Route::get('/releases', [ReleasesListController::class, 'index'])
        ->middleware('throttle:api-v1')
        ->name('api.v1.releases.index');

    Route::get('/releases/{version}/download', [ReleaseDownloadController::class, 'redirect'])
        ->middleware('throttle:api-v1')
        ->where('version', '[\w.\-+]+')
        ->name('api.v1.releases.download');

    Route::get('/releases/{version}/serve', [ReleaseDownloadController::class, 'serve'])
        ->middleware('throttle:api-v1')
        ->where('version', '[\w.\-+]+')
        ->name('api.v1.releases.serve');

    Route::get('/changelog', [ChangelogController::class, 'index'])
        ->middleware('throttle:api-v1')
        ->name('api.v1.changelog.index');

    // Stats públicas del crowdsource (sin client_key) — el cliente las muestra
    // en su HUD para que el user vea cuánto ha contribuido la comunidad.
    Route::get('/templates/digits/stats', [DigitTemplatesController::class, 'stats'])
        ->middleware('throttle:api-v1')
        ->name('api.v1.templates.digits.stats');

    Route::get('/ocr/samples/stats', [OcrSamplesController::class, 'stats'])
        ->middleware('throttle:api-v1')
        ->name('api.v1.ocr.samples.stats');

    // POST /ocr/samples es público (sólo X-Anon-Token, sin X-Client-Key)
    // porque la client_key viaja dentro del bundle y queremos que cualquier
    // install legítimo pueda contribuir samples sin filtrar la key. El abuso
    // se mitiga con: rate-limit doble (anon + IP), magic-bytes PNG, dims
    // ≤1024x512, anti-PII por regex y `status` server-side antes de entrar
    // al consenso (api-v1-ocr-samples limiter).
    Route::middleware(['anon_token'])->group(function () {
        Route::post('/ocr/samples', [OcrSamplesController::class, 'store'])
            ->middleware('throttle:api-v1-ocr-samples')
            ->name('api.v1.ocr.samples.store');
    });

    // GET items Lu4 — público para sync periódica del cliente. Necesita client_key
    // para filtrar tráfico pero no anon_token (es de solo lectura).
    Route::middleware(['client_key'])->group(function () {
        Route::get('/items/lu4', [Lu4Controller::class, 'index'])
            ->middleware('throttle:api-v1')
            ->name('api.v1.items.lu4.index');
    });

    // Tracking público de ticket por token secreto. Sin client_key porque el
    // tracking_token ES el secreto compartido con el bot.
    Route::get('/tickets/{token}', [TicketsController::class, 'showByTrackingToken'])
        ->middleware('throttle:api-v1')
        ->where('token', '[A-Za-z0-9]{40}')
        ->name('api.v1.tickets.show');

    // Endpoints que ingestan datos del cliente — requieren client key + anon token.
    Route::middleware(['client_key', 'anon_token'])->group(function () {

        Route::post('/templates/digits', [DigitTemplatesController::class, 'store'])
            ->middleware('throttle:api-v1-upload')
            ->name('api.v1.templates.digits.upload');

        // Descarga del ZIP de consenso (top-N clusters por dígito).
        Route::get('/templates/digits', [DigitTemplatesController::class, 'index'])
            ->middleware('throttle:api-v1')
            ->name('api.v1.templates.digits.index');

        Route::post('/telemetry/session', [TelemetrySessionController::class, 'store'])
            ->middleware('throttle:api-v1-telemetry')
            ->name('api.v1.telemetry.session.store');

        Route::post('/items/lu4/report-unknown', [Lu4Controller::class, 'reportUnknown'])
            ->middleware('throttle:api-v1')
            ->name('api.v1.items.lu4.report_unknown');

        Route::post('/tickets', [TicketsController::class, 'store'])
            ->middleware('throttle:api-v1-tickets')
            ->name('api.v1.tickets.store');

        Route::post('/crashes', [CrashReportController::class, 'store'])
            ->middleware('throttle:api-v1-crashes')
            ->name('api.v1.crashes.store');

        Route::delete('/me/data', [MeDataController::class, 'destroy'])
            ->name('api.v1.me.data.destroy');
    });

    // Endpoints admin (CI / scripts de build). Auth con Sanctum personal access
    // token + ability 'release:upload'. Token se genera con:
    //   php artisan releases:make-token <admin-email>
    Route::middleware(['auth:sanctum', 'ability:release:upload'])->group(function () {
        Route::post('/admin/releases', [ReleasesPublishApiController::class, 'store'])
            ->middleware('throttle:api-v1')
            ->name('api.v1.admin.releases.store');

        // Metadata-only PATCH — backfill release_notes / critical_update / etc.
        // on a release whose binary is already in S3 and must not be touched.
        Route::patch('/admin/releases/{version}', [ReleasesPublishApiController::class, 'update'])
            ->middleware('throttle:api-v1')
            ->where('version', '[\w.\-+]+')
            ->name('api.v1.admin.releases.update');
    });
});

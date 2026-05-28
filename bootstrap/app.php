<?php

use App\Contexts\ClientApi\Application\Middleware\LogApiRequest;
use App\Contexts\ClientApi\Application\Middleware\RequireClientKey;
use App\Contexts\ClientApi\Application\Middleware\ResolveAnonToken;
use App\Http\Middleware\EnsureCpMembershipApproved;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsureCpMembershipApproved::class,
            \App\Http\Middleware\TrackActivity::class,
        ]);

        // Fuerza JSON en todas las rutas /api/v1/* — evita que un cliente
        // sin Accept: application/json acabe leyendo HTML de la SPA tras
        // un redirect implícito de validation/auth.
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'anon_token' => ResolveAnonToken::class,
            'api.log' => LogApiRequest::class,
            'client_key' => RequireClientKey::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

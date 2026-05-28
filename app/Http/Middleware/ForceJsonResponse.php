<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forzar que todas las rutas /api/v1/* respondan JSON,
 * independientemente del header Accept del cliente.
 *
 * Sin esto, un cliente que no manda `Accept: application/json` puede
 * acabar viendo HTML (la SPA) cuando una validación falla, porque
 * Laravel detecta que el request no "expectsJson" y redirige.
 * El cliente interpreta 200 OK + HTML como éxito y pierde el sample
 * que iba a subir.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');
        return $next($request);
    }
}

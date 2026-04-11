<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureCpMembershipApproved
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || ! $user->cp_id) {
            return $next($request);
        }

        if (($user->membership_status ?? 'approved') !== 'pending') {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        $allowedRouteNames = [
            'dashboard',
            'profile.edit',
            'profile.update',
            'profile.destroy',
            'logout',
            'itemsdb.index',
            'verification.notice',
            'verification.verify',
            'verification.send',
            'password.confirm',
            'password.update',
        ];

        if ($routeName && in_array($routeName, $allowedRouteNames, true)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Tu cuenta está pendiente de aprobación por el líder del CP.',
            ], 403);
        }

        return redirect()
            ->route('dashboard')
            ->with('error', 'Tu cuenta está pendiente de aprobación por el líder del CP.');
    }
}

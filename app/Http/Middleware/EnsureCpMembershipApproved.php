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

        $status = $user->membership_status ?? 'approved';

        if ($status === 'pending') {
            $message = 'Tu cuenta está pendiente de aprobación por el líder del CP.';
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
            $redirectRouteName = 'dashboard';
        } elseif ($status === 'banned') {
            $message = 'Tu cuenta ha sido excluida del CP.';
            $allowedRouteNames = [
                'excluded',
                'logout',
                'verification.notice',
                'verification.verify',
                'verification.send',
                'password.confirm',
                'password.update',
            ];
            $redirectRouteName = 'excluded';
        } else {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if ($routeName && in_array($routeName, $allowedRouteNames, true)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $message,
            ], 403);
        }

        return redirect()
            ->route($redirectRouteName)
            ->with('error', $message);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Contexts\Identity\Domain\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonateController extends Controller
{
    /**
     * Start impersonating a user.
     */
    public function take(Request $request, User $user)
    {
        // Only admins can start impersonation
        if (optional($request->user()->role)->name !== 'admin') {
            abort(403);
        }

        // Prevent self-impersonation
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'No puedes impersonarte a ti mismo.');
        }

        // Store original admin ID in session
        $request->session()->put('impersonated_by', $request->user()->id);

        // Swap user
        Auth::loginUsingId($user->id);

        return redirect()->route('dashboard')->with('success', "Ahora estás viendo la aplicación como {$user->name}.");
    }

    /**
     * Stop impersonating and return to admin.
     */
    public function leave(Request $request)
    {
        if (! $request->session()->has('impersonated_by')) {
            return redirect()->route('dashboard');
        }

        $adminId = $request->session()->pull('impersonated_by');
        $admin = User::findOrFail($adminId);

        Auth::login($admin);

        return redirect()->route('dashboard')->with('success', 'Has vuelto a tu cuenta de administrador.');
    }
}

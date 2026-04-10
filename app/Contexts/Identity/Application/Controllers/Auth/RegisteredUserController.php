<?php

namespace App\Contexts\Identity\Application\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Party\Domain\Models\ConstParty;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Register', [
            'inviteCode' => $request->query('invite')
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'invite_code' => ['required', 'string'],
        ], [
            'invite_code.required' => 'An invite code is required to register on this system.'
        ]);

        $cp = ConstParty::where('invite_code', $request->invite_code)->first();

        if (!$cp) {
            throw ValidationException::withMessages([
                'invite_code' => 'The provided invite code is invalid.'
            ]);
        }

        $isLeaderRegistration = is_null($cp->leader_id);
        
        $roleName = $isLeaderRegistration ? 'cp_leader' : 'member';
        $role = Role::where('name', $roleName)->first();

        if (!$role) {
            throw ValidationException::withMessages([
                'invite_code' => 'System error: Required role ' . $roleName . ' is missing.'
            ]);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'cp_id' => $cp->id,
            'role_id' => $role->id,
        ]);

        // If this was the leader registration, assign the leader
        if ($isLeaderRegistration) {
            $cp->update(['leader_id' => $user->id]);
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}

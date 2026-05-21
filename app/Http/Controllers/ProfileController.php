<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $user->loadMissing('characters.l2Class', 'mainClass');

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'characters' => $user->characters->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'l2_class_id' => $c->l2_class_id,
                'class_name' => $c->l2Class?->name,
                'race' => $c->race,
                'level' => $c->level,
            ])->values(),
            'main_character' => [
                'name' => $user->name,
                'l2_class_id' => $user->main_class_id,
                'class_name' => $user->mainClass?->name,
                'race' => $user->main_race,
                'level' => $user->main_level,
            ],
            'l2_classes' => \App\Contexts\Identity\Domain\Models\L2Class::orderBy('race')->orderBy('class_type')->orderBy('name')
                ->get(['id', 'name', 'race', 'class_type']),
            'l2_races' => \App\Contexts\Identity\Application\Services\CharacterCatalogService::RACES,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        // Derive main_race from the chosen main_class so the two stay
        // coherent. Anything the form posted as `main_race` is overwritten
        // when a class id is present — same pattern the Character model
        // uses for secondary chars.
        if ($request->user()->main_class_id) {
            $class = \App\Contexts\Identity\Domain\Models\L2Class::find($request->user()->main_class_id);
            if ($class) {
                $request->user()->main_race = $class->race;
            }
        } else {
            $request->user()->main_race = null;
        }

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        // Sync language preference to session immediately
        $langPref = $request->input('language_preference', 'system');
        if ($langPref !== 'system' && in_array($langPref, ['en', 'es'], true)) {
            $request->session()->put('locale', $langPref);
        } else {
            $request->session()->forget('locale');
        }

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}

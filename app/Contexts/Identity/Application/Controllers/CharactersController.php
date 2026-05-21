<?php

namespace App\Contexts\Identity\Application\Controllers;

use App\Contexts\Identity\Application\Requests\CharacterStoreRequest;
use App\Contexts\Identity\Application\Requests\CharacterUpdateRequest;
use App\Contexts\Identity\Application\Services\CharacterCatalogService;
use App\Contexts\Identity\Domain\Models\Character;
use App\Contexts\Identity\Domain\Models\L2Class;
use App\Contexts\Identity\Domain\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CharactersController extends Controller
{
    public function __construct(private readonly CharacterCatalogService $catalog) {}

    /**
     * Dedicated page for managing the user's L2 characters. Lives outside
     * /profile so the perfil de usuario stays focused on web-account
     * fields (email, password, preferences) and the L2 nicks/classes have
     * their own home.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $user->loadMissing('characters.l2Class', 'mainClass');

        return Inertia::render('Characters/Index', [
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
            'l2_classes' => L2Class::orderBy('race')->orderBy('class_type')->orderBy('name')
                ->get(['id', 'name', 'race', 'class_type']),
            'l2_races' => CharacterCatalogService::RACES,
        ]);
    }

    public function store(CharacterStoreRequest $request): RedirectResponse
    {
        Character::create([
            'user_id' => $request->user()->id,
            'name' => $request->input('name'),
            'l2_class_id' => $request->input('l2_class_id'),
            'level' => $request->input('level'),
        ]);

        return back()->with('success', 'Personaje creado.');
    }

    public function update(CharacterUpdateRequest $request, Character $character): RedirectResponse
    {
        $this->ensureOwned($request, $character);

        $character->update([
            'name' => $request->input('name'),
            'l2_class_id' => $request->input('l2_class_id'),
            'level' => $request->input('level'),
        ]);

        return back()->with('success', 'Personaje actualizado.');
    }

    public function destroy(Request $request, Character $character): RedirectResponse
    {
        $this->ensureOwned($request, $character);
        $character->delete();
        return back()->with('success', 'Personaje eliminado.');
    }

    /**
     * Lightweight JSON listing of a CP member's characters, for the loot
     * modal dropdown. Only callable by another approved member of the same
     * CP (so leaders can pick a char for an attendee but randoms can't
     * enumerate someone's chars).
     */
    public function listForUser(Request $request, User $user): JsonResponse
    {
        $current = $request->user();
        if (!$current) {
            abort(403);
        }
        $sameCp = $current->cp_id && $user->cp_id && $current->cp_id === $user->cp_id;
        if (!$sameCp && $current->role?->name !== 'admin') {
            abort(403);
        }

        $chars = $user->characters()->with('l2Class:id,name,race')->orderBy('name')->get();
        $main = [
            'id' => null,
            'name' => $user->name,
            'class_name' => $user->main_class_id ? L2Class::where('id', $user->main_class_id)->value('name') : null,
            'race' => $user->main_race,
            'level' => $user->main_level,
            'is_main' => true,
        ];

        return response()->json([
            'main' => $main,
            'secondaries' => $chars->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'class_name' => $c->l2Class?->name,
                'race' => $c->race,
                'level' => $c->level,
                'is_main' => false,
            ])->values(),
        ]);
    }

    private function ensureOwned(Request $request, Character $character): void
    {
        if ($character->user_id !== $request->user()->id) {
            abort(403, 'Solo puedes editar tus propios personajes.');
        }
    }
}

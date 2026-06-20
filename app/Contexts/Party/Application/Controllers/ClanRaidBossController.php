<?php

namespace App\Contexts\Party\Application\Controllers;

use App\Contexts\Party\Domain\Models\Clan;
use App\Contexts\Party\Domain\Models\ClanCpMembership;
use App\Contexts\Party\Domain\Models\ClanRaidBoss;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClanRaidBossController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');

        $raidBosses = ClanRaidBoss::where('clan_id', $clan->id)
            ->with('updatedBy')
            ->orderBy('is_epic', 'desc')
            ->orderBy('level', 'desc')
            ->orderBy('name')
            ->get()
            ->toArray();

        return Inertia::render('Clan/RaidBosses', [
            'raidBosses'     => $raidBosses,
            'userMembership' => [
                'role' => $membership->role,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($membership->isAdmin(), 403, 'Solo administradores pueden añadir jefes de raid.');

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'level'         => ['nullable', 'integer', 'min:1', 'max:99'],
            'respawn_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
            'is_epic'       => ['boolean'],
        ]);

        ClanRaidBoss::create([
            'clan_id'       => $clan->id,
            'name'          => $data['name'],
            'level'         => $data['level'] ?? null,
            'respawn_hours' => $data['respawn_hours'] ?? 4,
            'is_epic'       => $data['is_epic'] ?? false,
            'status'        => ClanRaidBoss::STATUS_UNKNOWN,
        ]);

        return back()->with('success', 'Jefe de raid añadido.');
    }

    public function update(Request $request, ClanRaidBoss $boss): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($boss->clan_id === $clan->id, 403, 'Este jefe no pertenece a tu clan.');

        $data = $request->validate([
            'status'       => ['required', 'string', 'in:unknown,alive,killed'],
            'last_killed_at' => ['nullable', 'date'],
        ]);

        if ($data['status'] === ClanRaidBoss::STATUS_KILLED) {
            $killedAt = $data['last_killed_at'] ? now()->parse($data['last_killed_at']) : null;
            $boss->markKilled($killedAt);
        } else {
            $boss->status = $data['status'];
        }

        $boss->updated_by_user_id = $user->id;
        $boss->save();

        return back()->with('success', 'Estado actualizado.');
    }

    public function updateConfig(Request $request, ClanRaidBoss $boss): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($boss->clan_id === $clan->id, 403, 'Este jefe no pertenece a tu clan.');
        abort_unless($membership->isAdmin(), 403, 'Solo administradores pueden editar la configuración del jefe.');

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'level'         => ['nullable', 'integer', 'min:1', 'max:99'],
            'respawn_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
            'is_epic'       => ['boolean'],
        ]);

        $boss->update($data);

        return back()->with('success', 'Configuración del jefe actualizada.');
    }

    public function destroy(Request $request, ClanRaidBoss $boss): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($boss->clan_id === $clan->id, 403, 'Este jefe no pertenece a tu clan.');
        abort_unless($membership->isAdmin(), 403, 'Solo administradores pueden eliminar jefes de raid.');

        $boss->delete();

        return back()->with('success', 'Jefe de raid eliminado.');
    }
}

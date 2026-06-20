<?php

namespace App\Contexts\Party\Application\Controllers;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Party\Application\Services\ClanDkpService;
use App\Contexts\Party\Domain\Models\Clan;
use App\Contexts\Party\Domain\Models\ClanCpMembership;
use App\Contexts\Party\Domain\Models\ClanDkpAdjustment;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClanDkpAdjustmentController extends Controller
{
    public function __construct(private readonly ClanDkpService $dkpService) {}

    public function history(Request $request, User $user): Response
    {
        $caller = $request->user();
        $cp = $caller->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($membership->isAdmin(), 403, 'Solo administradores pueden ver el historial de DKP.');

        $adjustments = ClanDkpAdjustment::where('clan_id', $clan->id)
            ->where('user_id', $user->id)
            ->with('adjustedBy:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($adj) => [
                'id'                => $adj->id,
                'amount'            => $adj->amount,
                'reason'            => $adj->reason,
                'adjusted_by_name'  => $adj->adjustedBy?->name,
                'created_at'        => $adj->created_at?->toIso8601String(),
            ])
            ->values()
            ->toArray();

        $earned = $this->dkpService->earned($user, $clan);
        $spent  = $this->dkpService->spent($user, $clan);
        $net    = $this->dkpService->balance($user, $clan);

        return Inertia::render('Clan/DkpHistory', [
            'targetUser'     => $user->only('id', 'name'),
            'adjustments'    => $adjustments,
            'earned'         => $earned,
            'spent'          => $spent,
            'balance'        => $net,
            'userMembership' => ['role' => $membership->role],
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        $caller = $request->user();
        $cp = $caller->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($membership->isAdmin(), 403, 'Solo administradores pueden ajustar DKP.');

        $data = $request->validate([
            'amount' => ['required', 'integer', 'not_in:0', 'between:-99999,99999'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        ClanDkpAdjustment::create([
            'clan_id'              => $clan->id,
            'user_id'              => $user->id,
            'amount'               => $data['amount'],
            'reason'               => $data['reason'] ?? null,
            'adjusted_by_user_id'  => $caller->id,
        ]);

        return back()->with('success', 'Ajuste de DKP registrado.');
    }

    public function destroy(Request $request, ClanDkpAdjustment $adjustment): RedirectResponse
    {
        $caller = $request->user();
        $cp = $caller->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($membership->isAdmin(), 403, 'Solo administradores pueden revertir ajustes de DKP.');
        abort_unless($adjustment->clan_id === $clan->id, 403, 'Este ajuste no pertenece a tu clan.');

        $adjustment->delete();

        return back()->with('success', 'Ajuste de DKP revertido.');
    }
}

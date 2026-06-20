<?php

namespace App\Contexts\Party\Application\Controllers;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Party\Application\Services\ClanDkpService;
use App\Contexts\Party\Domain\Models\Clan;
use App\Contexts\Party\Domain\Models\ClanCpMembership;
use App\Contexts\Party\Domain\Models\ClanDkpAdjustment;
use App\Contexts\Party\Domain\Models\ConstParty;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClanController extends Controller
{
    public function __construct(private readonly ClanDkpService $dkpService) {}

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function resolveUserClan(Request $request): array
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        return [$user, $cp, $membership, $clan];
    }

    // ── Overview / create-or-join ────────────────────────────────────────────

    public function index(Request $request): Response
    {
        [, $cp, $membership, $clan] = $this->resolveUserClan($request);

        if (! $clan) {
            return Inertia::render('Clan/Join', [
                'hasCp' => (bool) $cp,
                'userRole' => $request->user()->role?->name,
            ]);
        }

        $clanCps = ClanCpMembership::with(['constParty.leader:id,name'])
            ->where('clan_id', $clan->id)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->constParty->id,
                'name' => $m->constParty->name,
                'server' => $m->constParty->server,
                'chronicle' => $m->constParty->chronicle,
                'logo_url' => $m->constParty->logo_url,
                'leader_name' => $m->constParty->leader?->name,
                'member_count' => $m->constParty->members()->count(),
                'role' => $m->role,
                'joined_at' => $m->joined_at?->toIso8601String(),
            ]);

        $nextEvent = $clan->events()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at')
            ->first(['id', 'name', 'event_type', 'scheduled_at']);

        return Inertia::render('Clan/Index', [
            'clan' => [
                'id' => $clan->id,
                'name' => $clan->name,
                'description' => $clan->description,
                'logo_url' => $clan->logo_url,
                'invite_code' => $membership->isAdmin() ? $clan->invite_code : null,
            ],
            'clanCps' => $clanCps,
            'totalMembers' => $clanCps->sum('member_count'),
            'nextEvent' => $nextEvent ? [
                'id' => $nextEvent->id,
                'name' => $nextEvent->name,
                'event_type' => $nextEvent->event_type,
                'scheduled_at' => $nextEvent->scheduled_at?->toIso8601String(),
            ] : null,
            'userMembership' => [
                'role' => $membership->role,
                'can_approve_attendance' => $membership->can_approve_attendance,
            ],
        ]);
    }

    // ── Create clan ─────────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;

        abort_unless($cp, 403, 'Necesitas pertenecer a una CP para crear un clan.');
        abort_unless(
            in_array($user->role?->name, ['cp_leader', 'accountant', 'admin'], true),
            403,
            'Solo el líder de una CP puede crear un clan.'
        );
        abort_if(
            ClanCpMembership::where('cp_id', $cp->id)->exists(),
            422,
            'Tu CP ya pertenece a un clan.'
        );

        $data = $request->validate([
            'name' => 'required|string|max:60|unique:clans,name',
            'description' => 'nullable|string|max:500',
        ]);

        $clan = DB::transaction(function () use ($user, $cp, $data) {
            $clan = new Clan($data);
            $clan->forceFill([
                'invite_code' => strtoupper(Str::random(8)),
                'created_by_user_id' => $user->id,
            ])->save();

            ClanCpMembership::create([
                'clan_id' => $clan->id,
                'cp_id' => $cp->id,
                'role' => ClanCpMembership::ROLE_OWNER,
                'can_approve_attendance' => true,
                'joined_at' => now(),
            ]);

            return $clan;
        });

        return redirect()->route('clan.index')->with('success', "Clan \"{$clan->name}\" creado con éxito.");
    }

    // ── Join clan by invite code ─────────────────────────────────────────────

    public function join(Request $request): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;

        abort_unless($cp, 403, 'Necesitas pertenecer a una CP para unirte a un clan.');
        abort_unless(
            in_array($user->role?->name, ['cp_leader', 'accountant', 'admin'], true),
            403,
            'Solo el líder de una CP puede unir su CP a un clan.'
        );
        abort_if(
            ClanCpMembership::where('cp_id', $cp->id)->exists(),
            422,
            'Tu CP ya pertenece a un clan.'
        );

        $data = $request->validate([
            'invite_code' => 'required|string|size:8',
        ]);

        $clan = Clan::where('invite_code', strtoupper($data['invite_code']))
            ->where('is_active', true)
            ->first();

        abort_unless($clan, 404, 'Código de invitación inválido o clan inactivo.');

        ClanCpMembership::create([
            'clan_id' => $clan->id,
            'cp_id' => $cp->id,
            'role' => ClanCpMembership::ROLE_MEMBER,
            'can_approve_attendance' => false,
            'joined_at' => now(),
        ]);

        return redirect()->route('clan.index')->with('success', "Te has unido al clan \"{$clan->name}\".");
    }

    // ── Cross-CP member directory ────────────────────────────────────────────

    public function members(Request $request): Response
    {
        [, , $membership, $clan] = $this->resolveUserClan($request);
        abort_unless($clan, 403);

        $balances = $this->dkpService->balanceMapForClan($clan);

        $members = User::query()
            ->join('clan_cp_memberships as ccm', 'ccm.cp_id', '=', 'users.cp_id')
            ->join('const_parties as cp', 'cp.id', '=', 'users.cp_id')
            ->where('ccm.clan_id', $clan->id)
            ->where('users.membership_status', '!=', 'banned')
            ->select([
                'users.id',
                'users.name',
                'users.cp_id',
                'cp.name as cp_name',
                'users.main_class',
                'users.main_level',
                'users.main_race',
            ])
            ->orderBy('users.name')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'cp_id' => $u->cp_id,
                'cp_name' => $u->cp_name,
                'main_class' => $u->main_class,
                'main_level' => $u->main_level,
                'clan_dkp' => $balances[$u->id] ?? 0,
            ]);

        $clanCps = ClanCpMembership::where('clan_id', $clan->id)
            ->with('constParty:id,name')
            ->get()
            ->map(fn ($m) => ['id' => $m->cp_id, 'name' => $m->constParty?->name]);

        return Inertia::render('Clan/Members', [
            'members' => $members,
            'clanCps' => $clanCps,
            'userMembership' => [
                'role' => $membership->role,
                'can_approve_attendance' => $membership->can_approve_attendance,
            ],
        ]);
    }

    // ── Settings ─────────────────────────────────────────────────────────────

    public function settings(Request $request): Response
    {
        [$user, $cp, $membership, $clan] = $this->resolveUserClan($request);
        abort_unless($clan && $membership?->isAdmin(), 403);

        $clanCps = ClanCpMembership::with(['constParty:id,name,server,chronicle'])
            ->where('clan_id', $clan->id)
            ->get()
            ->map(fn ($m) => [
                'cp_id' => $m->cp_id,
                'cp_name' => $m->constParty?->name,
                'server' => $m->constParty?->server,
                'role' => $m->role,
                'can_approve_attendance' => $m->can_approve_attendance,
                'joined_at' => $m->joined_at?->toIso8601String(),
            ]);

        return Inertia::render('Clan/Settings', [
            'clan' => [
                'id' => $clan->id,
                'name' => $clan->name,
                'description' => $clan->description,
                'logo_url' => $clan->logo_url,
                'invite_code' => $clan->invite_code,
            ],
            'clanCps' => $clanCps,
            'userMembership' => ['role' => $membership->role],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        [, , $membership, $clan] = $this->resolveUserClan($request);
        abort_unless($clan && $membership?->isAdmin(), 403);

        $data = $request->validate([
            'name' => 'required|string|max:60|unique:clans,name,' . $clan->id,
            'description' => 'nullable|string|max:500',
            'logo' => 'nullable|image|max:3072',
        ]);

        $clan->update(['name' => $data['name'], 'description' => $data['description']]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store("clan-logos/{$clan->id}", 'public');
            $clan->update(['logo_path' => $path]);
        }

        return back()->with('success', 'Ajustes del clan actualizados.');
    }

    public function regenerateInviteCode(Request $request): RedirectResponse
    {
        [, , $membership, $clan] = $this->resolveUserClan($request);
        abort_unless($clan && $membership?->isAdmin(), 403);

        $clan->update(['invite_code' => strtoupper(Str::random(8))]);

        return back()->with('success', 'Código de invitación regenerado.');
    }

    // ── CP role management ──────────────────────────────────────────────────

    public function updateCpRole(Request $request, ConstParty $cp): RedirectResponse
    {
        [, , $membership, $clan] = $this->resolveUserClan($request);
        abort_unless($clan && $membership?->isOwner(), 403);

        $data = $request->validate([
            'role' => 'required|in:admin,member',
        ]);

        $target = ClanCpMembership::where('clan_id', $clan->id)->where('cp_id', $cp->id)->first();
        abort_unless($target && $target->role !== ClanCpMembership::ROLE_OWNER, 403);

        $target->update(['role' => $data['role']]);

        return back()->with('success', 'Rol actualizado.');
    }

    public function toggleApprover(Request $request, ConstParty $cp): RedirectResponse
    {
        [, , $membership, $clan] = $this->resolveUserClan($request);
        abort_unless($clan && $membership?->isAdmin(), 403);

        $target = ClanCpMembership::where('clan_id', $clan->id)->where('cp_id', $cp->id)->first();
        abort_unless($target, 404);

        $target->update(['can_approve_attendance' => ! $target->can_approve_attendance]);

        return back()->with('success', 'Permiso de aprobación actualizado.');
    }

    public function removeCp(Request $request, ConstParty $cp): RedirectResponse
    {
        [, , $membership, $clan] = $this->resolveUserClan($request);
        abort_unless($clan && $membership?->isAdmin(), 403);

        $target = ClanCpMembership::where('clan_id', $clan->id)->where('cp_id', $cp->id)->first();
        abort_unless($target && $target->role !== ClanCpMembership::ROLE_OWNER, 403, 'No puedes expulsar al owner.');

        $target->delete();

        return back()->with('success', 'CP eliminada del clan.');
    }

    // ── Leave / Destroy ──────────────────────────────────────────────────────

    public function leave(Request $request): RedirectResponse
    {
        [$user, $cp, $membership, $clan] = $this->resolveUserClan($request);
        abort_unless($clan && $membership, 403);
        abort_if($membership->isOwner(), 422, 'El owner no puede abandonar el clan. Transfiere el ownership o disuelve el clan.');

        $membership->delete();

        return redirect()->route('clan.index')->with('success', 'Has abandonado el clan.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        [, , $membership, $clan] = $this->resolveUserClan($request);
        abort_unless($clan && $membership?->isOwner(), 403);

        $data = $request->validate([
            'confirmation' => 'required|string',
        ]);

        abort_unless($data['confirmation'] === $clan->name, 422, 'El nombre del clan no coincide.');

        $clan->delete();

        return redirect()->route('clan.index')->with('success', 'Clan disuelto.');
    }
}

<?php

namespace App\Contexts\Party\Application\Controllers;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Party\Domain\Models\ConstParty;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ConstPartyController extends Controller
{
    /**
     * Admin-only full CP roster. Drives /system/cps — replaces the dashboard
     * widget when you need search/filter/sort and per-row KPIs.
     */
    public function adminIndex(Request $request): Response
    {
        if ($request->user()->role?->name !== 'admin') {
            abort(403);
        }

        $cps = ConstParty::query()
            ->with('leader:id,name,email')
            ->withCount(['members as approved_members_count' => function ($q) {
                $q->where('membership_status', '!=', 'banned');
            }])
            ->selectSub(
                DB::table('loot_reports')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('loot_reports.cp_id', 'const_parties.id')
                    ->where('status', 'confirmed'),
                'confirmed_reports_count'
            )
            ->selectSub(
                DB::table('loot_reports')->selectRaw('MAX(created_at)')
                    ->whereColumn('loot_reports.cp_id', 'const_parties.id'),
                'last_activity_at'
            )
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get();

        $cpFunds = $this->cpFundTotals($cps->pluck('id')->all());

        $payload = $cps->map(fn ($cp) => [
            'id' => $cp->id,
            'name' => $cp->name,
            'server' => $cp->server,
            'chronicle' => $cp->chronicle,
            'is_active' => (bool) $cp->is_active,
            'created_at' => $cp->created_at?->toIso8601String(),
            'leader' => $cp->leader ? [
                'id' => $cp->leader->id,
                'name' => $cp->leader->name,
                'email' => $cp->leader->email,
            ] : null,
            'members_count' => (int) $cp->approved_members_count,
            'confirmed_reports_count' => (int) ($cp->confirmed_reports_count ?? 0),
            'cp_fund_adena' => (int) ($cpFunds[$cp->id] ?? 0),
            'last_activity_at' => $cp->last_activity_at,
            'invite_code' => $cp->invite_code,
        ])->values();

        $pendingRequests = DB::table('cp_requests')
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get(['id', 'cp_name', 'server', 'chronicle', 'leader_name', 'contact_email', 'created_at']);

        return Inertia::render('System/Cps/Index', [
            'cps' => $payload,
            'pendingRequests' => $pendingRequests,
            'chronicles' => ['C1', 'C2', 'C3', 'C4', 'C5', 'IL', 'CT1', 'GF', 'HB', 'Classic', 'LU4'],
        ]);
    }

    /**
     * Per-CP balance of the implicit "CP fund": adena entries on confirmed
     * loot/sell reports that did NOT go to a specific user (i.e. anything
     * left over in the warehouse). Computed in a single query so the index
     * doesn't N+1.
     *
     * @return array<int,int> cpId => adena
     */
    private function cpFundTotals(array $cpIds): array
    {
        if (empty($cpIds)) {
            return [];
        }
        $rows = DB::table('loot_entries')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->join('items', 'items.id', '=', 'loot_entries.item_id')
            ->whereIn('loot_reports.cp_id', $cpIds)
            ->where('loot_reports.status', 'confirmed')
            ->whereRaw('LOWER(items.name) = ?', ['adena'])
            ->groupBy('loot_reports.cp_id')
            ->selectRaw('loot_reports.cp_id as cp_id, SUM(loot_entries.amount) as total')
            ->get();

        $totalsByCp = [];
        foreach ($rows as $row) {
            $totalsByCp[$row->cp_id] = (int) $row->total;
        }

        // Subtract adena paid out as PointsLog rows for each CP — those have
        // already left the fund and landed in user balances.
        $paid = DB::table('points_logs')
            ->whereIn('cp_id', $cpIds)
            ->where('action_type', 'ADENA_GAIN')
            ->groupBy('cp_id')
            ->selectRaw('cp_id, SUM(adena) as paid')
            ->pluck('paid', 'cp_id')
            ->toArray();

        foreach ($cpIds as $id) {
            $totalsByCp[$id] = ($totalsByCp[$id] ?? 0) - (int) ($paid[$id] ?? 0);
        }

        return $totalsByCp;
    }

    public function adminUpdate(Request $request, ConstParty $cp): RedirectResponse
    {
        if ($request->user()->role?->name !== 'admin') {
            abort(403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255|unique:const_parties,name,'.$cp->id,
            'server' => 'nullable|string|max:255',
            'chronicle' => 'required|string|in:C1,C2,C3,C4,C5,IL,CT1,GF,HB,Classic,LU4',
        ]);

        $cp->update($data);

        return back()->with('success', "CP \"{$cp->name}\" actualizada.");
    }

    public function store(Request $request)
    {
        if ($request->user()->role?->name !== 'admin') {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:const_parties,name',
            'server' => 'nullable|string|max:255',
            'chronicle' => 'required|string|in:C1,C2,C3,C4,C5,IL,CT1,GF,HB,Classic,LU4',
        ]);

        $inviteCode = Str::random(12);

        $cp = ConstParty::create([
            'name' => $request->name,
            'server' => $request->input('server'),
            'chronicle' => $request->chronicle,
            'invite_code' => $inviteCode,
        ]);

        $magicLink = route('register', ['invite' => $inviteCode]);

        return back()->with('success', [
            'message' => 'Const Party creada exitosamente.',
            'link' => $magicLink,
            'cp_name' => $cp->name,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $cp = $user->cp;

        if (! $cp || $user->id !== $cp->leader_id) {
            abort(403, 'Solo el líder fundador de la CP puede modificar los ajustes generales.');
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:const_parties,name,'.$cp->id,
            'server' => 'nullable|string|max:255',
            'logo' => 'nullable|image|max:3072', // 3MB
            'image_proof_required' => 'nullable|boolean',
        ]);

        $cp->update([
            'name' => $request->name,
            'server' => $request->server,
            'image_proof_required' => $request->boolean('image_proof_required', true),
        ]);

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $path = $file->store("cp-logos/{$cp->id}", 'public');
            $cp->update(['logo_path' => $path]);
        }

        return back()->with('success', 'Ajustes de la Const Party actualizados correctamente.');
    }

    public function toggleActive(Request $request, ConstParty $cp)
    {
        if ($request->user()->role->name !== 'admin') {
            abort(403);
        }

        // `is_active` is intentionally not fillable; bypass via forceFill
        // after we've checked the actor is admin (handled above).
        $cp->forceFill(['is_active' => ! $cp->is_active])->save();

        $status = $cp->is_active ? 'activada' : 'desactivada';

        return back()->with('success', "CP {$cp->name} {$status}.");
    }

    public function destroy(Request $request, ConstParty $cp)
    {
        if ($request->user()->role->name !== 'admin') {
            abort(403);
        }

        $memberCount = User::where('cp_id', $cp->id)
            ->where('membership_status', '!=', 'banned')
            ->count();

        if ($memberCount > 0) {
            return back()->withErrors(['cp' => "No se puede eliminar la CP \"{$cp->name}\" porque tiene {$memberCount} miembros. Desactívala primero."]);
        }

        DB::transaction(function () use ($cp) {
            User::where('cp_id', $cp->id)->update(['cp_id' => null]);
            $cp->delete();
        });

        return redirect()->route('dashboard')->with('success', "CP \"{$cp->name}\" eliminada.");
    }
}

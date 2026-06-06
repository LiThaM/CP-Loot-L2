<?php

namespace App\Contexts\Party\Application\Controllers;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Party\Domain\Models\TrackerContribution;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Drives the opt-in DKP-style value tracker. Members of a CP that has
 * `tracker_enabled = true` get a dedicated page with the leaderboard
 * (sum of points by member) and the chronological contributions list.
 *
 * Leaders can also manually log EVENT bonuses (raid attendance, weekly
 * pots, ad-hoc rewards) on top of the auto-derived material rows the
 * `TrackerContributionService` writes when a LootReport is confirmed.
 */
class TrackerController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user && $user->cp_id, 403);

        $cp = $user->cp;
        abort_unless($cp && $cp->tracker_enabled, 404);

        $cpId = $cp->id;
        $isLeader = $user->role?->name === 'cp_leader';

        $leaderboard = DB::table('tracker_contributions')
            ->join('users', 'users.id', '=', 'tracker_contributions.user_id')
            ->where('tracker_contributions.cp_id', $cpId)
            ->groupBy('tracker_contributions.user_id', 'users.name')
            ->orderByDesc(DB::raw('SUM(tracker_contributions.points)'))
            ->get([
                'tracker_contributions.user_id',
                'users.name',
                DB::raw('SUM(tracker_contributions.points) as total_points'),
                DB::raw('COUNT(tracker_contributions.id) as entries'),
            ]);

        $contributions = TrackerContribution::query()
            ->where('cp_id', $cpId)
            ->with(['user:id,name', 'createdBy:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->through(fn (TrackerContribution $c) => [
                'id' => $c->id,
                'created_at' => $c->created_at?->toIso8601String(),
                'user_id' => $c->user_id,
                'user_name' => $c->user?->name,
                'type' => $c->type,
                'badge' => $c->badge,
                'points' => (float) $c->points,
                'description' => $c->description,
                'source_loot_entry_id' => $c->source_loot_entry_id,
                'created_by_name' => $c->createdBy?->name,
            ]);

        $members = User::where('cp_id', $cpId)
            ->where('membership_status', '!=', 'banned')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Party/Tracker', [
            'cp' => [
                'id' => $cp->id,
                'name' => $cp->name,
                'tracker_divisor' => (int) $cp->tracker_divisor,
                'tracker_enabled_at' => $cp->tracker_enabled_at?->toIso8601String(),
            ],
            'leaderboard' => $leaderboard,
            'contributions' => $contributions,
            'members' => $members,
            'isLeader' => $isLeader,
        ]);
    }

    public function storeContribution(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->role?->name === 'cp_leader' && $user->cp_id, 403);

        $cp = $user->cp;
        abort_unless($cp && $cp->tracker_enabled, 404);

        $data = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'description' => 'required|string|max:255',
            'points' => 'required|numeric|min:0.01|max:99999999.99',
            'is_event' => 'sometimes|boolean',
        ]);

        // Make sure every member belongs to this CP and isn't banned.
        $validIds = User::whereIn('id', $data['user_ids'])
            ->where('cp_id', $cp->id)
            ->where('membership_status', '!=', 'banned')
            ->pluck('id')
            ->all();

        if (count($validIds) !== count(array_unique($data['user_ids']))) {
            abort(422, 'One or more members do not belong to this CP.');
        }

        $isEvent = (bool) ($data['is_event'] ?? false);
        $n = count($validIds);
        $badge = $isEvent
            ? TrackerContribution::BADGE_EVENT
            : ($n === 1 ? TrackerContribution::BADGE_SOLO : TrackerContribution::BADGE_PARTY_PREFIX.$n);
        $type = $isEvent ? TrackerContribution::TYPE_EVENT : TrackerContribution::TYPE_MATERIAL;

        // EVENT bonuses are flat per-member. SOLO/PARTY entered manually
        // split the points value across the chosen members (same shape as
        // the auto-derive path).
        $pointsPer = $isEvent
            ? round((float) $data['points'], 2)
            : round(((float) $data['points']) / max(1, $n), 2);

        DB::transaction(function () use ($validIds, $cp, $user, $type, $pointsPer, $data, $badge) {
            $now = now();
            $rows = [];
            foreach ($validIds as $uid) {
                $rows[] = [
                    'cp_id' => $cp->id,
                    'user_id' => $uid,
                    'type' => $type,
                    'points' => $pointsPer,
                    'description' => $data['description'],
                    'badge' => $badge,
                    'source_loot_entry_id' => null,
                    'created_by_user_id' => $user->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('tracker_contributions')->insert($rows);
        });

        return back()->with('success', 'Contribución registrada.');
    }

    public function destroyContribution(Request $request, TrackerContribution $contribution): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->role?->name === 'cp_leader' && $user->cp_id, 403);
        abort_unless($contribution->cp_id === $user->cp_id, 403);

        $contribution->delete();

        return back()->with('success', 'Contribución borrada.');
    }
}

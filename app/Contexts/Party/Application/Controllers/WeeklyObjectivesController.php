<?php

namespace App\Contexts\Party\Application\Controllers;

use App\Contexts\Party\Application\Services\TrackerContributionService;
use App\Contexts\Party\Domain\Models\CpWeeklyObjective;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WeeklyObjectivesController extends Controller
{
    /**
     * Leader adds a weekly objective (item + target quantity + points
     * multiplier). Adding first PURGES the CP's completed objectives (reached
     * target or stamped) so the board keeps the still-pending old ones plus
     * the new — incentivising finishing what's left.
     */
    public function store(Request $request, TrackerContributionService $tracker)
    {
        $user = $request->user();
        if (! in_array($user->role?->name, ['admin', 'cp_leader', 'accountant'], true)) {
            abort(403, 'Solo el líder de la CP puede gestionar objetivos.');
        }
        if (! $user->cp_id) {
            abort(403, 'No perteneces a ninguna CP.');
        }

        $data = $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'target_quantity' => ['required', 'integer', 'min:1', 'max:99999999'],
            'multiplier' => ['required', 'numeric', 'min:1', 'max:9.99'],
        ]);

        $cpId = $user->cp_id;

        // Purge completed objectives (stamped, or progress already at target).
        foreach (CpWeeklyObjective::where('cp_id', $cpId)->get() as $obj) {
            $done = $obj->completed_at !== null
                || $tracker->objectiveProgress($cpId, $obj->item_id, $obj->created_at) >= $obj->target_quantity;
            if ($done) {
                $obj->delete();
            }
        }

        if (CpWeeklyObjective::where('cp_id', $cpId)->where('item_id', $data['item_id'])->exists()) {
            return back()->withErrors(['item_id' => 'Ya hay un objetivo activo para ese item.']);
        }

        CpWeeklyObjective::create([
            'cp_id' => $cpId,
            'item_id' => $data['item_id'],
            'target_quantity' => $data['target_quantity'],
            'multiplier' => $data['multiplier'],
            'created_by_user_id' => $user->id,
        ]);

        return back()->with('success', 'Objetivo semanal añadido.');
    }

    public function destroy(Request $request, CpWeeklyObjective $objective)
    {
        $user = $request->user();
        if (! in_array($user->role?->name, ['admin', 'cp_leader', 'accountant'], true)) {
            abort(403);
        }
        if ($user->role?->name !== 'admin' && $objective->cp_id !== $user->cp_id) {
            abort(403);
        }

        $objective->delete();

        return back()->with('success', 'Objetivo eliminado.');
    }
}

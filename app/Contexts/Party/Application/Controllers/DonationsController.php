<?php

namespace App\Contexts\Party\Application\Controllers;

use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Party\Domain\Models\CpDonation;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DonationsController extends Controller
{
    /**
     * A member donates an item to the CP common fund. This is a recognition
     * record (it feeds the donations ranking + weekly goal KPI); it does NOT
     * touch the loot/warehouse balance pipeline. The item is valued at its
     * effective price (market price, NPC sell-back as fallback) so adena and
     * item donations are comparable in the KPI.
     */
    public function donateItem(Request $request)
    {
        $user = $request->user();
        if (! $user->cp_id) {
            abort(403, 'No perteneces a ninguna CP.');
        }

        $data = $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999999999'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $item = Item::find($data['item_id']);
        $unit = (int) ($item->market_price ?? $item->npc_sell_price ?? 0);
        $value = $unit * (int) $data['quantity'];

        CpDonation::create([
            'cp_id' => $user->cp_id,
            'user_id' => $user->id,
            'type' => 'item',
            'item_id' => $item->id,
            'quantity' => (int) $data['quantity'],
            'adena_value' => $value,
            'note' => $data['note'] ?? $item->name,
        ]);

        return back()->with('success', '¡Gracias por tu donación de items al fondo de la CP!');
    }

    /**
     * Leader/admin sets (or clears) their CP's rolling-7-day donation goal.
     * Passing null or 0 clears it (KPI hidden).
     */
    public function setWeeklyGoal(Request $request)
    {
        $user = $request->user();
        $role = $user->role?->name;
        if (! in_array($role, ['admin', 'cp_leader'], true)) {
            abort(403, 'Solo el líder de la CP puede fijar el objetivo semanal.');
        }
        if (! $user->cp) {
            abort(403, 'No perteneces a ninguna CP.');
        }

        $data = $request->validate([
            'goal' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
        ]);

        $goal = (int) ($data['goal'] ?? 0);
        $user->cp->forceFill(['weekly_donation_goal' => $goal > 0 ? $goal : null])->save();

        return back()->with('success', 'Objetivo semanal actualizado.');
    }
}

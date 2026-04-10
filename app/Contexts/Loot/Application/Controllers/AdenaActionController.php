<?php

namespace App\Contexts\Loot\Application\Controllers;

use App\Contexts\Party\Domain\Models\PointsLog;
use App\Contexts\Identity\Domain\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdenaActionController extends Controller
{
    /**
     * Store an Adena transaction (Gain or Payout).
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|integer', // Positive for gain, negative for payout
            'description' => 'required|string|max:255',
        ]);

        $currentUser = $request->user();
        $targetUser = User::findOrFail($request->user_id);

        // Security: Leader can only manage their own CP members
        $isAdmin = $currentUser->role->name === 'admin';
        $isLeader = $currentUser->cp_id && $currentUser->id === $currentUser->cp->leader_id;

        if (!$isAdmin) {
            if (!$isLeader || $targetUser->cp_id !== $currentUser->cp_id) {
                abort(403, 'No tienes permiso para gestionar el saldo de este usuario.');
            }
        }

        PointsLog::create([
            'cp_id' => $targetUser->cp_id,
            'user_id' => $targetUser->id,
            'action_type' => $request->amount < 0 ? 'ADENA_PAYOUT' : 'ADENA_GAIN',
            'points' => 0, // Points are separate
            'adena' => $request->amount,
            'description' => $request->description,
        ]);

        $msg = $request->amount < 0 ? "Pago de Adena registrado." : "Abono de Adena registrado.";
        return back()->with('success', $msg);
    }
}

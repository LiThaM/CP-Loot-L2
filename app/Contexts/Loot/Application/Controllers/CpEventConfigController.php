<?php

namespace App\Contexts\Loot\Application\Controllers;

use App\Contexts\Loot\Domain\Models\CpEventConfig;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CpEventConfigController extends Controller
{
    /**
     * Update or create points configuration for a CP event type.
     */
    public function update(Request $request)
    {
        $request->validate([
            'event_type' => 'required|string',
            'points' => 'required|integer|min:0',
        ]);

        $user = $request->user();

        if (! $user->cp_id || $user->id !== $user->cp->leader_id) {
            abort(403, 'Solo el líder de la CP puede configurar puntos.');
        }

        CpEventConfig::updateOrCreate(
            ['cp_id' => $user->cp_id, 'event_type' => $request->event_type],
            ['points' => $request->points]
        );

        return back()->with('success', "Puntos actualizados para {$request->event_type}.");
    }
}

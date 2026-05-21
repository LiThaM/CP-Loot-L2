<?php

namespace App\Contexts\Loot\Application\Controllers;

use App\Contexts\Loot\Application\Requests\CraftBulkPlanRequest;
use App\Contexts\Loot\Application\Services\CraftBulkPlannerService;
use App\Contexts\Loot\Domain\Models\Recipe;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CraftBulkController extends Controller
{
    public function __construct(private readonly CraftBulkPlannerService $planner) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $cp = $user->cp;
        if (!$cp) {
            abort(403, 'Necesitas pertenecer a una CP.');
        }
        $role = $user->role?->name;
        if (!in_array($role, ['admin', 'cp_leader', 'accountant'], true)) {
            abort(403, 'Solo el líder fundador, co-líderes y contables pueden usar el craft masivo.');
        }

        return Inertia::render('Party/CraftBulk/Index', [
            'cp' => [
                'id' => $cp->id,
                'name' => $cp->name,
                'chronicle' => $cp->chronicle ?: 'IL',
            ],
        ]);
    }

    public function plan(CraftBulkPlanRequest $request): JsonResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $chronicle = $cp->chronicle ?: 'IL';

        $orders = collect($request->validated('orders'))
            ->map(fn ($o) => ['recipe_id' => (int) $o['recipe_id'], 'qty' => (int) $o['qty']])
            ->all();

        // Reject orders mixing chronicles — we plan per-CP and that CP has
        // exactly one chronicle.
        $foreign = Recipe::whereIn('id', array_column($orders, 'recipe_id'))
            ->where('chronicle', '!=', $chronicle)
            ->pluck('name')
            ->all();
        if (!empty($foreign)) {
            return response()->json([
                'error' => 'chronicle_mismatch',
                'message' => 'Some recipes are not in your CP chronicle ('.$chronicle.'): '.implode(', ', $foreign),
            ], 422);
        }

        $result = $this->planner->plan($cp->id, $chronicle, $orders);

        return response()->json($result);
    }
}

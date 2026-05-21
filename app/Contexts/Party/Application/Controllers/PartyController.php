<?php

namespace App\Contexts\Party\Application\Controllers;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\CpEventConfig;
use App\Contexts\Loot\Domain\Models\CpRecipe;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\LootEntry;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Loot\Domain\Models\LootReportAttendee;
use App\Contexts\Loot\Domain\Models\Recipe;
use App\Contexts\Party\Domain\Models\ConstParty;
use App\Contexts\Party\Domain\Models\PointsLog;
use App\Contexts\System\Domain\Models\AuditLog;
use App\Contexts\Identity\Domain\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PartyController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user->cp_id) {
            return Inertia::render('Party/Index', ['has_cp' => false]);
        }

        $cp = $user->cp->load('leader');
        $roleName = $user->role?->name;
        $canManageWarehouse = $roleName === 'admin' || $roleName === 'cp_leader' || $roleName === 'accountant';

        $members = User::where('cp_id', $user->cp_id)
            ->withSum('pointsLogs as total_points', 'points')
            ->orderByDesc('total_points')
            ->get();

        $memberIds = $members->pluck('id')->all();
        $adenaGainedByUser = PointsLog::query()
            ->selectRaw('user_id, SUM(adena) as total')
            ->where('cp_id', $user->cp_id)
            ->where('action_type', 'ADENA_GAIN')
            ->whereIn('user_id', $memberIds)
            ->groupBy('user_id')
            ->pluck('total', 'user_id');
        $adenaPaidByUser = PointsLog::query()
            ->selectRaw('user_id, SUM(adena) as total')
            ->where('cp_id', $user->cp_id)
            ->whereIn('action_type', ['ADENA_PAYOUT', 'ADENA_OFFSET'])
            ->whereIn('user_id', $memberIds)
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $members = $members->map(function ($m) use ($adenaGainedByUser, $adenaPaidByUser) {
            $g = (int) ($adenaGainedByUser[$m->id] ?? 0);
            $p = abs((int) ($adenaPaidByUser[$m->id] ?? 0));
            $m->setAttribute('adena_gained', $g);
            $m->setAttribute('adena_paid', $p);
            $m->setAttribute('adena_owed', max(0, $g - $p));

            return $m;
        });

        $eventConfigs = CpEventConfig::where('cp_id', $user->cp_id)->get();

        $warehouseIncoming = LootEntry::query()
            ->select([
                'items.id',
                'items.name',
                'items.icon_name',
                'items.image_url',
                'items.grade',
                DB::raw('SUM(loot_entries.amount) as incoming_amount'),
                // Newest report that contributed this item — used to put
                // freshly farmed loot at the top of the warehouse list.
                DB::raw('MAX(loot_reports.created_at) as last_added_at'),
            ])
            ->join('items', 'items.id', '=', 'loot_entries.item_id')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->where('loot_reports.cp_id', $user->cp_id)
            ->where('loot_reports.status', 'confirmed')
            ->whereNotIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME'])
            ->whereRaw('LOWER(items.name) != ?', ['adena'])
            ->groupBy('items.id', 'items.name', 'items.icon_name', 'items.image_url', 'items.grade')
            ->get()
            ->keyBy('id');

        $warehouseOutgoing = LootEntry::query()
            ->select([
                'items.id',
                DB::raw('SUM(loot_entries.amount) as outgoing_amount'),
            ])
            ->join('items', 'items.id', '=', 'loot_entries.item_id')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->where('loot_reports.cp_id', $user->cp_id)
            ->where('loot_reports.status', 'confirmed')
            ->whereIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME'])
            ->whereRaw('LOWER(items.name) != ?', ['adena'])
            ->groupBy('items.id')
            ->pluck('outgoing_amount', 'id');

        $warehouseItems = $warehouseIncoming->map(function ($row) use ($warehouseOutgoing) {
            $out = (int) ($warehouseOutgoing[$row->id] ?? 0);
            $in = (int) ($row->incoming_amount ?? 0);
            $row->total_amount = max(0, $in - $out);
            unset($row->incoming_amount);

            return $row;
        })->values()
          ->filter(fn ($row) => (int) $row->total_amount > 0)
          // Sort by most recently added DESC, then by stock DESC as tie-
          // breaker so newly farmed loot lands at the top of the list.
          ->sortBy([
              ['last_added_at', 'desc'],
              ['total_amount', 'desc'],
          ])->values();

        $warehouseAmountsByItemId = $warehouseItems->pluck('total_amount', 'id');

        $craftableRecipeIdByItemId = $this->craftableRecipeIdByItemId((string) ($cp->chronicle ?: 'IL'));

        // Batch-load all sub-recipes for craftable materials (for craft_potential calculation)
        $subRecipeIds = collect($craftableRecipeIdByItemId)->unique()->values()->all();
        $subRecipesById = ! empty($subRecipeIds)
            ? Recipe::whereIn('id', $subRecipeIds)->with('materials.item')->get()->keyBy('id')
            : collect();

        $cpRecipes = CpRecipe::query()
            ->where('cp_id', $user->cp_id)
            ->with(['recipe.outputItem', 'recipe.outputs.item', 'recipe.materials.item', 'recipe.recipeItem'])
            // Newest pin first — the leader is normally looking at what they
            // just added. `priority` stays as a manual tie-breaker.
            ->orderByDesc('created_at')
            ->orderBy('priority')
            ->get()
            ->map(function (CpRecipe $cpRecipe) use ($warehouseAmountsByItemId, $craftableRecipeIdByItemId, $subRecipesById) {
                $recipe = $cpRecipe->recipe;
                $materials = $recipe?->materials ?? collect();
                $outputs = $recipe?->outputs ?? collect();

                $materialsList = $materials->map(function ($mat) use ($warehouseAmountsByItemId) {
                    $need = (int) ($mat->quantity ?? 1);
                    $have = (int) ($warehouseAmountsByItemId[$mat->item_id] ?? 0);

                    return [
                        'item_id' => $mat->item_id,
                        'name' => $mat->item?->name,
                        'image_url' => $mat->item?->image_url,
                        'need' => $need,
                        'have' => $have,
                        'missing' => max(0, $need - $have),
                    ];
                })->map(function (array $m) use ($craftableRecipeIdByItemId) {
                    $m['craft_recipe_id'] = $craftableRecipeIdByItemId[(int) $m['item_id']] ?? null;
                    $m['craftable'] = $m['craft_recipe_id'] !== null;

                    return $m;
                })->map(function (array $m) use ($warehouseAmountsByItemId, $subRecipesById) {
                    $m['craft_potential'] = 0;
                    $m['craft_potential_limited_by'] = null;

                    if (($m['missing'] ?? 0) <= 0 || ! ($m['craft_recipe_id'] ?? null)) {
                        return $m;
                    }

                    $subRecipe = $subRecipesById[$m['craft_recipe_id']] ?? null;
                    if (! $subRecipe || $subRecipe->materials->isEmpty()) {
                        return $m;
                    }

                    $outputQty = max(1, (int) ($subRecipe->output_quantity ?? 1));
                    $minCrafts = PHP_INT_MAX;
                    $bottleneck = null;

                    foreach ($subRecipe->materials as $subMat) {
                        $subNeed = (int) ($subMat->quantity ?? 1);
                        if ($subNeed <= 0) {
                            continue;
                        }
                        $subHave = (int) ($warehouseAmountsByItemId[$subMat->item_id] ?? 0);
                        $possible = intdiv($subHave, $subNeed);
                        if ($possible < $minCrafts) {
                            $minCrafts = $possible;
                            $bottleneck = $subMat->item?->name;
                        }
                    }

                    if ($minCrafts === PHP_INT_MAX) {
                        $minCrafts = 0;
                    }

                    $canProduce = $minCrafts * $outputQty;
                    $m['craft_potential'] = min($canProduce, $m['missing']);
                    $m['craft_potential_limited_by'] = $bottleneck;

                    return $m;
                })->values();

                // Inject Recipe scroll as a "material" for UI visibility
                if ($recipe?->recipe_item_id) {
                    $recipeItem = $recipe->recipeItem;
                    $have = (int) ($warehouseAmountsByItemId[$recipe->recipe_item_id] ?? 0);
                    $materialsList->prepend([
                        'item_id' => $recipe->recipe_item_id,
                        'name' => $recipeItem?->name ?? 'Receta ' . $recipe->name,
                        'image_url' => $recipeItem?->image_url,
                        'need' => 1,
                        'have' => $have,
                        'missing' => max(0, 1 - $have),
                        'craft_recipe_id' => null,
                        'craftable' => false,
                        'is_recipe' => true,
                    ]);
                }

                return [
                    'id' => $cpRecipe->id,
                    'priority' => $cpRecipe->priority,
                    'recipe' => $recipe ? [
                        'id' => $recipe->id,
                        'name' => $recipe->name,
                        'success_rate' => $recipe->success_rate,
                        'output_item' => $recipe->outputItem ? [
                            'id' => $recipe->outputItem->id,
                            'name' => $recipe->outputItem->name,
                            'image_url' => $recipe->outputItem->image_url,
                        ] : null,
                        'outputs' => $outputs->map(function ($out) {
                            return [
                                'item_id' => $out->item_id,
                                'name' => $out->item?->name,
                                'image_url' => $out->item?->image_url,
                                'quantity' => (int) ($out->quantity ?? 1),
                                'chance' => $out->chance,
                            ];
                        })->values(),
                        'materials' => $materialsList,
                    ] : null,
                ];
            })
            ->values();

        $adenaIn = LootEntry::query()
            ->join('items', 'items.id', '=', 'loot_entries.item_id')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->where('loot_reports.cp_id', $user->cp_id)
            ->where('loot_reports.status', 'confirmed')
            ->whereNotIn('loot_reports.event_type', ['ADENA_PAYOUT', 'ADENA_GRANT'])
            ->whereRaw('LOWER(items.name) = ?', ['adena'])
            ->sum('loot_entries.amount');

        $adenaPaidSum = PointsLog::where('cp_id', $user->cp_id)
            ->where('action_type', 'ADENA_PAYOUT')
            ->sum('adena');
        $warehouseAdena = max(0, (int) $adenaIn + (int) $adenaPaidSum);

        $cpAdenaGained = (int) PointsLog::where('cp_id', $user->cp_id)
            ->where('action_type', 'ADENA_GAIN')
            ->sum('adena');
        $cpAdenaPaid = abs((int) PointsLog::where('cp_id', $user->cp_id)
            ->whereIn('action_type', ['ADENA_PAYOUT', 'ADENA_OFFSET'])
            ->sum('adena'));
        $cpAdenaOwed = max(0, $cpAdenaGained - $cpAdenaPaid);

        $tab = $request->string('tab')->lower()->toString();
        if ($tab === '') {
            $tab = Str::of((string) $request->route('tab'))->lower()->toString();
        }
        $initialTab = in_array($tab, ['members', 'warehouse_cp', 'crafting', 'config'], true) ? $tab : 'members';

        return Inertia::render('Party/Index', [
            'has_cp' => true,
            'cp' => $cp,
            'members' => $members,
            'eventConfigs' => $eventConfigs,
            'warehouseItems' => $warehouseItems,
            'warehouseAdena' => $warehouseAdena,
            'warehouseAdenaNet' => $warehouseAdena - $cpAdenaOwed,
            'cpAdenaOwed' => $cpAdenaOwed,
            'cpAdenaPaid' => $cpAdenaPaid,
            'cpRecipes' => $cpRecipes,
            'canManageWarehouse' => $canManageWarehouse,
            'isLeader' => $user->id === $cp->leader_id,
            'initialTab' => $initialTab,
            // Non-admins must not see `admin` in the role dropdown of the
            // user-edit modal. The backend already rejects the assignment
            // in UserManagementController::update, but until now the
            // dropdown still listed it because this payload sent
            // Role::all() unconditionally.
            'roles' => $user->role?->name === 'admin'
                ? Role::all()
                : Role::query()->whereIn('name', ['cp_leader', 'accountant', 'member'])->get(),
            'cps' => in_array($user->role?->name, ['admin']) ? ConstParty::all() : [],
            'isAdmin' => $user->role?->name === 'admin',
        ]);
    }

    public function approveMember(Request $request, User $user)
    {
        $actor = $request->user();

        if (! $actor->cp_id || (int) $actor->cp_id !== (int) $user->cp_id) {
            abort(403);
        }

        $isLeader = (int) $actor->id === (int) ($actor->cp?->leader_id ?? 0);
        $isAdmin = ($actor->role?->name ?? null) === 'admin';

        if (! $isLeader && ! $isAdmin) {
            abort(403);
        }

        $user->update(['membership_status' => 'approved']);

        return back()->with('success', 'Miembro aprobado.');
    }

    public function resetPoints(Request $request)
    {
        $actor = $request->user();

        if (! $actor->cp_id) {
            abort(403);
        }

        $isLeader = (int) $actor->id === (int) ($actor->cp?->leader_id ?? 0);
        $isAdmin = ($actor->role?->name ?? null) === 'admin';

        if (! $isLeader && ! $isAdmin) {
            abort(403);
        }

        $cpId = $actor->cp_id;

        DB::transaction(function () use ($cpId, $actor) {
            $affected = PointsLog::where('cp_id', $cpId)
                ->where('points', '!=', 0)
                ->update(['points' => 0]);

            $audit = AuditLog::create([
                'entity_type' => 'ConstParty',
                'entity_id' => $cpId,
                'user_id' => $actor->id,
                'action' => 'DKP_RESET',
                'old_values' => null,
                'new_values' => ['rows_zeroed' => (int) $affected],
            ]);

            $memberIds = User::where('cp_id', $cpId)->pluck('id');
            $now = now();
            $summary = "{$actor->name} reinició los puntos DKP de la CP";
            $rows = $memberIds->map(fn ($rid) => [
                'audit_log_id' => $audit->id,
                'recipient_user_id' => $rid,
                'actor_user_id' => $actor->id,
                'entity_type' => 'ConstParty',
                'entity_id' => $cpId,
                'action' => 'DKP_RESET',
                'summary' => $summary,
                'meta' => json_encode(['rows_zeroed' => (int) $affected]),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            if (! empty($rows)) {
                DB::table('audit_alerts')->insert($rows);
            }
        });

        return back()->with('success', 'Puntos DKP reiniciados.');
    }

    public function myWarehouse(Request $request)
    {
        $user = $request->user();

        if (! $user->cp_id) {
            return Inertia::render('Warehouse/Index', ['has_cp' => false]);
        }

        $myAdenaGained = (int) PointsLog::where('cp_id', $user->cp_id)
            ->where('user_id', $user->id)
            ->where('action_type', 'ADENA_GAIN')
            ->sum('adena');
        $myAdenaPaid = abs((int) PointsLog::where('cp_id', $user->cp_id)
            ->where('user_id', $user->id)
            ->whereIn('action_type', ['ADENA_PAYOUT', 'ADENA_OFFSET'])
            ->sum('adena'));
        $myAdenaOwed = max(0, $myAdenaGained - $myAdenaPaid);

        $myAssigned = LootEntry::query()
            ->select([
                'items.id',
                'items.name',
                'items.icon_name',
                'items.image_url',
                'items.grade',
                DB::raw('SUM(loot_entries.amount) as assigned_amount'),
                DB::raw('MAX(loot_reports.created_at) as last_added_at'),
            ])
            ->join('items', 'items.id', '=', 'loot_entries.item_id')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->where('loot_reports.cp_id', $user->cp_id)
            ->where('loot_reports.status', 'confirmed')
            ->where('loot_reports.event_type', 'ASSIGN')
            ->where('loot_entries.awarded_to', $user->id)
            ->whereRaw('LOWER(items.name) != ?', ['adena'])
            ->groupBy('items.id', 'items.name', 'items.icon_name', 'items.image_url', 'items.grade')
            ->get()
            ->keyBy('id');

        $myReturned = LootEntry::query()
            ->select([
                'items.id',
                DB::raw('SUM(loot_entries.amount) as returned_amount'),
            ])
            ->join('items', 'items.id', '=', 'loot_entries.item_id')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->where('loot_reports.cp_id', $user->cp_id)
            ->whereIn('loot_reports.status', ['pending', 'confirmed'])
            ->where('loot_reports.event_type', 'RETURN')
            ->where('loot_entries.awarded_to', $user->id)
            ->whereRaw('LOWER(items.name) != ?', ['adena'])
            ->groupBy('items.id')
            ->pluck('returned_amount', 'id');

        $myItems = $myAssigned->map(function ($row) use ($myReturned) {
            $returned = (int) ($myReturned[$row->id] ?? 0);
            $assigned = (int) ($row->assigned_amount ?? 0);
            $row->total_amount = max(0, $assigned - $returned);
            unset($row->assigned_amount);

            return $row;
        })->values()
          ->filter(fn ($row) => (int) $row->total_amount > 0)
          ->sortBy([
              ['last_added_at', 'desc'],
              ['total_amount', 'desc'],
          ])->values();

        return Inertia::render('Warehouse/Index', [
            'has_cp' => true,
            'myItems' => $myItems,
            'myAdenaOwed' => $myAdenaOwed,
            'myAdenaPaid' => $myAdenaPaid,
        ]);
    }

    public function memberWarehouse(Request $request, User $user)
    {
        $current = $request->user();
        $roleName = $current->role?->name;
        $isAdmin = $roleName === 'admin';

        if (! $current->cp_id && ! $isAdmin) {
            abort(403);
        }

        if (! $isAdmin && $user->cp_id !== $current->cp_id) {
            abort(403);
        }

        $cpId = $isAdmin ? $user->cp_id : $current->cp_id;

        $adenaGained = (int) PointsLog::where('cp_id', $cpId)
            ->where('user_id', $user->id)
            ->where('action_type', 'ADENA_GAIN')
            ->sum('adena');
        $adenaPaid = abs((int) PointsLog::where('cp_id', $cpId)
            ->where('user_id', $user->id)
            ->whereIn('action_type', ['ADENA_PAYOUT', 'ADENA_OFFSET'])
            ->sum('adena'));

        $assigned = LootEntry::query()
            ->select([
                'items.id',
                'items.name',
                'items.icon_name',
                'items.image_url',
                'items.grade',
                DB::raw('SUM(loot_entries.amount) as assigned_amount'),
                DB::raw('MAX(loot_reports.created_at) as last_added_at'),
            ])
            ->join('items', 'items.id', '=', 'loot_entries.item_id')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->where('loot_reports.event_type', 'ASSIGN')
            ->where('loot_entries.awarded_to', $user->id)
            ->whereRaw('LOWER(items.name) != ?', ['adena'])
            ->groupBy('items.id', 'items.name', 'items.icon_name', 'items.image_url', 'items.grade')
            ->get()
            ->keyBy('id');

        $returned = LootEntry::query()
            ->select([
                'items.id',
                DB::raw('SUM(loot_entries.amount) as returned_amount'),
            ])
            ->join('items', 'items.id', '=', 'loot_entries.item_id')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->where('loot_reports.cp_id', $cpId)
            ->whereIn('loot_reports.status', ['pending', 'confirmed'])
            ->where('loot_reports.event_type', 'RETURN')
            ->where('loot_entries.awarded_to', $user->id)
            ->whereRaw('LOWER(items.name) != ?', ['adena'])
            ->groupBy('items.id')
            ->pluck('returned_amount', 'id');

        $items = $assigned->map(function ($row) use ($returned) {
            $returnedAmount = (int) ($returned[$row->id] ?? 0);
            $assignedAmount = (int) ($row->assigned_amount ?? 0);
            $row->total_amount = max(0, $assignedAmount - $returnedAmount);
            unset($row->assigned_amount);

            return $row;
        })->values()
          ->filter(fn ($row) => (int) $row->total_amount > 0)
          ->sortBy([
              ['last_added_at', 'desc'],
              ['total_amount', 'desc'],
          ])->values();

        return response()->json([
            'user_id' => $user->id,
            'items' => $items,
            'adena_gained' => $adenaGained,
            'adena_paid' => $adenaPaid,
            'adena_owed' => max(0, $adenaGained - $adenaPaid),
        ]);
    }

    public function assign(Request $request)
    {
        $request->validate([
            'item_id' => 'required|exists:items,id',
            'amount' => 'required|integer|min:1',
            'user_id' => 'required|exists:users,id',
            'image_proof' => 'required|image|max:4096',
            'adena_offset' => 'nullable|integer|min:0',
        ]);

        $current = $request->user();
        $isAdmin = $current->role->name === 'admin';
        $isLeader = $current->role->name === 'cp_leader';
        $isAccountant = $current->role->name === 'accountant';
        if (! $isAdmin && ! ($isLeader || $isAccountant)) {
            abort(403, 'No tienes permiso para asignar ítems del warehouse.');
        }

        $targetUser = User::findOrFail($request->user_id);
        if (! $isAdmin && $targetUser->cp_id !== $current->cp_id) {
            abort(403, 'El miembro no pertenece a tu CP.');
        }

        $cpId = $isAdmin ? $targetUser->cp_id : $current->cp_id;

        $item = Item::findOrFail($request->item_id);
        if (strtolower($item->name) === 'adena') {
            return back()->withErrors(['item_id' => 'La Adena se gestiona como saldo, no como ítem del warehouse.']);
        }

        $adenaOffset = max(0, (int) $request->input('adena_offset', 0));
        if ($adenaOffset > 0) {
            $g = (int) PointsLog::where('cp_id', $cpId)
                ->where('user_id', $targetUser->id)
                ->where('action_type', 'ADENA_GAIN')
                ->sum('adena');
            $p = abs((int) PointsLog::where('cp_id', $cpId)
                ->where('user_id', $targetUser->id)
                ->whereIn('action_type', ['ADENA_PAYOUT', 'ADENA_OFFSET'])
                ->sum('adena'));
            $owed = max(0, $g - $p);
            if ($owed <= 0) {
                return back()->withErrors(['adena_offset' => 'El miembro no tiene Adena pendiente.']);
            }
            if ($adenaOffset > $owed) {
                return back()->withErrors(['adena_offset' => 'El descuento excede la Adena pendiente. Disponible: '.$owed]);
            }
        }

        try {
            DB::transaction(function () use ($request, $cpId, $current, $targetUser, $adenaOffset, $item) {
                Item::whereKey($item->id)->lockForUpdate()->first();

                $incoming = LootEntry::query()
                    ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
                    ->where('loot_reports.cp_id', $cpId)
                    ->where('loot_reports.status', 'confirmed')
                    ->whereNotIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME'])
                    ->where('loot_entries.item_id', $request->item_id)
                    ->sum('loot_entries.amount');

                $outgoing = LootEntry::query()
                    ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
                    ->where('loot_reports.cp_id', $cpId)
                    ->where('loot_reports.status', 'confirmed')
                    ->whereIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME'])
                    ->where('loot_entries.item_id', $request->item_id)
                    ->sum('loot_entries.amount');

                $available = max(0, (int) $incoming - (int) $outgoing);
                if ($available < (int) $request->amount) {
                    throw new \RuntimeException('INSUFFICIENT_STOCK:'.$available);
                }

            $report = LootReport::create([
                'cp_id' => $cpId,
                'requested_by_id' => $current->id,
                'event_type' => 'ASSIGN',
                'status' => 'confirmed',
                'image_proof' => null,
                'recipient_ids' => [$targetUser->id],
            ]);

            $file = $request->file('image_proof');
            $ext = $file->extension() ?: ($file->guessExtension() ?: 'jpg');
            $imagePath = $file->storeAs("transfers/{$cpId}", "{$report->id}.{$ext}", 'public');
            $report->image_proof = $imagePath;
            $report->save();

            LootEntry::create([
                'loot_report_id' => $report->id,
                'item_id' => $request->item_id,
                'awarded_to' => $targetUser->id,
                'amount' => $request->amount,
            ]);

            $item = Item::find($request->item_id);
            if ($adenaOffset > 0) {
                PointsLog::create([
                    'cp_id' => $cpId,
                    'user_id' => $targetUser->id,
                    'action_type' => 'ADENA_OFFSET',
                    'points' => 0,
                    'adena' => -$adenaOffset,
                    'description' => 'Descuento de Adena por asignación ('.$item?->name.') - Reporte #'.$report->id,
                ]);
            }
            $audit = AuditLog::create([
                'entity_type' => 'LootReport',
                'entity_id' => $report->id,
                'user_id' => $current->id,
                'action' => 'WAREHOUSE_ASSIGN',
                'old_values' => null,
                'new_values' => [
                    'item_id' => (int) $request->item_id,
                    'item_name' => $item?->name,
                    'amount' => (int) $request->amount,
                    'awarded_to' => (int) $targetUser->id,
                    'adena_offset' => (int) $adenaOffset,
                ],
            ]);
            $recipients = collect([$current->id, $targetUser->id]);
            $leaderId = optional($current->cp)->leader_id;
            if ($leaderId) {
                $recipients->push($leaderId);
            }
            $recipients = $recipients->unique()->values();
            $amountLabel = 'x'.number_format((int) $request->amount, 0, ',', '.');
            $offsetLabel = $adenaOffset > 0 ? ' (-'.number_format($adenaOffset, 0, ',', '.').' adena)' : '';
            $summary = "{$current->name} asignó {$item?->name} {$amountLabel} a {$targetUser->name}{$offsetLabel}";
            $now = now();
            $rows = $recipients->map(fn ($rid) => [
                'audit_log_id' => $audit->id,
                'recipient_user_id' => $rid,
                'actor_user_id' => $current->id,
                'entity_type' => 'LootReport',
                'entity_id' => $report->id,
                'action' => 'WAREHOUSE_ASSIGN',
                'summary' => $summary,
                'meta' => json_encode(['report_id' => $report->id, 'item_id' => (int) $request->item_id]),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            DB::table('audit_alerts')->insert($rows);
            });
        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'INSUFFICIENT_STOCK:')) {
                $available = (int) substr($e->getMessage(), strlen('INSUFFICIENT_STOCK:'));

                return back()->withErrors(['amount' => 'Stock insuficiente en el warehouse. Disponible: '.$available]);
            }
            throw $e;
        }

        return back()->with('success', 'Ítem asignado y registrado con la captura.');
    }

    public function sell(Request $request)
    {
        $request->validate([
            'item_id' => 'required|exists:items,id',
            'amount' => 'required|integer|min:1',
            'unit_price' => 'required|integer|min:1',
            // source_report_id is REQUIRED — the leader picks which farm
            // session is being liquidated so the right people get paid.
            // Falling back to "last farm with this item" was the bug.
            'source_report_id' => 'required|integer|exists:loot_reports,id',
            'cp_share_pct' => 'required|integer|min:0|max:100',
            // Legacy fields tolerated for back-compat, ignored if the new
            // ones arrive.
            'adena_distribution' => 'nullable|in:cp,attendees',
            'recipient_ids' => 'nullable|array',
            'recipient_ids.*' => 'integer|exists:users,id',
            'image_proof' => 'required|image|max:4096',
        ]);

        $current = $request->user();
        $roleName = $current->role?->name;
        $isLeader = $roleName === 'cp_leader';
        $isAccountant = $roleName === 'accountant';
        if ($roleName !== 'admin' && ! ($isLeader || $isAccountant)) {
            abort(403, 'No tienes permiso para vender ítems del warehouse.');
        }
        if (! $current->cp_id) {
            abort(403);
        }

        $cpId = $current->cp_id;

        $item = Item::findOrFail($request->item_id);
        if (strtolower($item->name) === 'adena') {
            return back()->withErrors(['item_id' => 'La Adena se gestiona como saldo, no se vende como ítem del warehouse.']);
        }

        $sourceReport = LootReport::with('attendees')->find($request->source_report_id);
        if (!$sourceReport || $sourceReport->cp_id !== $cpId || $sourceReport->status !== 'confirmed') {
            return back()->withErrors(['source_report_id' => 'La sesión de farm seleccionada no es válida para esta CP.']);
        }
        $sourceHasItem = LootEntry::where('loot_report_id', $sourceReport->id)
            ->where('item_id', $item->id)
            ->exists();
        if (!$sourceHasItem) {
            return back()->withErrors(['source_report_id' => 'Esa sesión de farm no contiene este ítem.']);
        }

        $incoming = LootEntry::query()
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->whereNotIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME'])
            ->where('loot_entries.item_id', $request->item_id)
            ->sum('loot_entries.amount');

        $outgoing = LootEntry::query()
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->whereIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME'])
            ->where('loot_entries.item_id', $request->item_id)
            ->sum('loot_entries.amount');

        $available = max(0, (int) $incoming - (int) $outgoing);
        if ($available < (int) $request->amount) {
            return back()->withErrors(['amount' => 'Stock insuficiente en el warehouse. Disponible: '.$available]);
        }

        $sourceAttendees = $sourceReport->attendees;
        if ($sourceAttendees->isEmpty() && (int) $request->cp_share_pct < 100) {
            return back()->withErrors(['source_report_id' => 'La sesión origen no tiene attendees registrados; o márcala con 100% al CP fund o resuelve la sesión añadiendo attendees.']);
        }

        $amount = (int) $request->amount;
        $unitPrice = (int) $request->unit_price;
        $cpSharePct = (int) $request->cp_share_pct;
        $totalAdena = $amount * $unitPrice;

        // Split math:
        //   cpShare    = floor(total * pct / 100)                       (intent)
        //   toAtt      = total - cpShare                                (intent)
        //   perAtt     = floor(toAtt / N)
        //   leftover   = toAtt - perAtt * N                             (rounding rest)
        //   cpFund     = cpShare + leftover                             (actual into vault)
        $cpShare = intdiv($totalAdena * $cpSharePct, 100);
        $count = $sourceAttendees->count();
        $toAttendees = $totalAdena - $cpShare;
        $perAttendee = $count > 0 ? intdiv($toAttendees, $count) : 0;
        $leftover = $count > 0 ? $toAttendees - ($perAttendee * $count) : $toAttendees;
        $cpFundFinal = $cpShare + $leftover;

        $memberIds = $sourceAttendees->where('is_external', false)->pluck('user_id')->filter()->all();

        try {
            DB::transaction(function () use ($request, $cpId, $current, $item, $amount, $unitPrice, $totalAdena, $sourceReport, $sourceAttendees, $cpSharePct, $cpShare, $perAttendee, $cpFundFinal, $memberIds) {
                Item::whereKey($item->id)->lockForUpdate()->first();

                $incoming = LootEntry::query()
                    ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
                    ->where('loot_reports.cp_id', $cpId)
                    ->where('loot_reports.status', 'confirmed')
                    ->whereNotIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME'])
                    ->where('loot_entries.item_id', $item->id)
                    ->sum('loot_entries.amount');

                $outgoing = LootEntry::query()
                    ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
                    ->where('loot_reports.cp_id', $cpId)
                    ->where('loot_reports.status', 'confirmed')
                    ->whereIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME'])
                    ->where('loot_entries.item_id', $item->id)
                    ->sum('loot_entries.amount');

                $available = max(0, (int) $incoming - (int) $outgoing);
                if ($available < (int) $amount) {
                    throw new \RuntimeException('INSUFFICIENT_STOCK:'.$available);
                }

            $report = LootReport::create([
                'cp_id' => $cpId,
                'requested_by_id' => $current->id,
                'event_type' => 'SELL',
                'status' => 'confirmed',
                'image_proof' => null,
                'recipient_ids' => !empty($memberIds) ? $memberIds : null,
                'adena_distribution' => $cpSharePct === 100 ? 'cp' : 'attendees',
                'cp_share_pct' => $cpSharePct,
            ]);

            $file = $request->file('image_proof');
            $ext = $file->extension() ?: ($file->guessExtension() ?: 'jpg');
            $imagePath = $file->storeAs("warehouse_sell/{$cpId}", "{$report->id}.{$ext}", 'public');
            $report->image_proof = $imagePath;
            $report->save();

            LootEntry::create([
                'loot_report_id' => $report->id,
                'item_id' => $item->id,
                'amount' => $amount,
            ]);

            $adenaItem = Item::whereRaw('LOWER(name) = ?', ['adena'])->first();
            if ($adenaItem) {
                LootEntry::create([
                    'loot_report_id' => $report->id,
                    'item_id' => $adenaItem->id,
                    'amount' => $totalAdena,
                ]);
            }

            // Persist the per-attendee share on the SELL report (not the
            // source farm) so we keep a per-sale history. External attendees
            // get a row with share_adena but no PointsLog — the leader pays
            // them outside the app and marks paid_at via the external
            // payouts page.
            foreach ($sourceAttendees as $att) {
                LootReportAttendee::create([
                    'loot_report_id' => $report->id,
                    'user_id' => $att->user_id,
                    'character_id' => $att->character_id, // inherit char from source farm
                    'external_name' => $att->external_name,
                    'is_external' => $att->is_external,
                    'share_adena' => $perAttendee,
                ]);

                if (!$att->is_external && $att->user_id && $perAttendee > 0) {
                    PointsLog::create([
                        'cp_id' => $cpId,
                        'user_id' => $att->user_id,
                        'action_type' => 'ADENA_GAIN',
                        'points' => 0,
                        'adena' => $perAttendee,
                        'description' => 'Split de venta ('.$item->name.') - Reporte #'.$report->id.' (origen #'.$sourceReport->id.')',
                    ]);
                }
            }

            $audit = AuditLog::create([
                'entity_type' => 'LootReport',
                'entity_id' => $report->id,
                'user_id' => $current->id,
                'action' => 'WAREHOUSE_SELL',
                'old_values' => null,
                'new_values' => [
                    'item_id' => (int) $item->id,
                    'item_name' => $item->name,
                    'amount' => (int) $amount,
                    'unit_price' => (int) $unitPrice,
                    'total' => (int) $totalAdena,
                    'cp_share_pct' => $cpSharePct,
                    'cp_share' => (int) $cpFundFinal,
                    'per_attendee' => (int) $perAttendee,
                    'attendees_count' => $sourceAttendees->count(),
                    'external_count' => $sourceAttendees->where('is_external', true)->count(),
                    'source_report_id' => $sourceReport->id,
                ],
            ]);
            $recipients = collect([$current->id]);
            $leaderId = optional($current->cp)->leader_id;
            if ($leaderId) {
                $recipients->push($leaderId);
            }
            foreach ($memberIds as $rid) {
                $recipients->push($rid);
            }
            $recipients = $recipients->unique()->values();
            $totalLabel = number_format((int) $totalAdena, 0, ',', '.');
            $summary = "{$current->name} vendió {$item->name} x{$amount} por {$totalLabel} Adena";
            $summary .= " (origen #{$sourceReport->id})";
            $now = now();
            $rows = $recipients->map(fn ($rid) => [
                'audit_log_id' => $audit->id,
                'recipient_user_id' => $rid,
                'actor_user_id' => $current->id,
                'entity_type' => 'LootReport',
                'entity_id' => $report->id,
                'action' => 'WAREHOUSE_SELL',
                'summary' => $summary,
                'meta' => json_encode(['report_id' => $report->id, 'source_report_id' => $sourceReport->id, 'item_id' => (int) $item->id]),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            DB::table('audit_alerts')->insert($rows);
            });
        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'INSUFFICIENT_STOCK:')) {
                $available = (int) substr($e->getMessage(), strlen('INSUFFICIENT_STOCK:'));

                return back()->withErrors(['amount' => 'Stock insuficiente en el warehouse. Disponible: '.$available]);
            }
            throw $e;
        }

        return back()->with('success', 'Venta registrada. Adena añadida al warehouse.');
    }

    public function defaultSellRecipients(Request $request)
    {
        $request->validate([
            'item_id' => 'required|exists:items,id',
        ]);

        $current = $request->user();
        $roleName = $current->role?->name;
        if (! in_array($roleName, ['admin', 'cp_leader', 'accountant'], true)) {
            abort(403);
        }
        if (! $current->cp_id && $roleName !== 'admin') {
            abort(403);
        }

        $cpId = $current->cp_id;

        $report = LootReport::query()
            ->select('loot_reports.*')
            ->join('loot_entries', 'loot_entries.loot_report_id', '=', 'loot_reports.id')
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->whereNotIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'RETURN'])
            ->where('loot_entries.item_id', $request->item_id)
            ->orderByDesc('loot_reports.id')
            ->first();

        $ids = [];
        if ($report && is_array($report->recipient_ids) && count($report->recipient_ids) > 0) {
            $ids = User::where('cp_id', $cpId)
                ->where('membership_status', '!=', 'banned')
                ->whereIn('id', $report->recipient_ids)
                ->pluck('id')
                ->all();
        } else {
            $ids = User::where('cp_id', $cpId)
                ->where('membership_status', '!=', 'banned')
                ->pluck('id')
                ->all();
        }

        return response()->json([
            'recipient_ids' => $ids,
        ]);
    }

    /**
     * Lists confirmed farm sessions that contain a given item and still have
     * unsold stock attributable to them — feeds the "Sesión de farm origen"
     * dropdown in the sell modal. The math: for each candidate report,
     * `farmed = sum of LootEntry.amount in that report for this item`, and
     * `sold = sum of LootEntry.amount in SELL reports whose audit row points
     * at this same source_report_id and item_id`. Pending = farmed - sold.
     */
    public function sellSourceCandidates(Request $request)
    {
        $request->validate([
            'item_id' => 'required|exists:items,id',
        ]);

        $current = $request->user();
        $roleName = $current->role?->name;
        if (! in_array($roleName, ['admin', 'cp_leader', 'accountant'], true)) {
            abort(403);
        }
        if (! $current->cp_id && $roleName !== 'admin') {
            abort(403);
        }

        $cpId = $current->cp_id;
        $itemId = (int) $request->item_id;

        $farms = LootReport::query()
            ->select('loot_reports.id', 'loot_reports.event_type', 'loot_reports.requested_by_id', 'loot_reports.created_at', 'loot_reports.cp_share_pct')
            ->selectSub(
                LootEntry::query()
                    ->selectRaw('SUM(amount)')
                    ->whereColumn('loot_entries.loot_report_id', 'loot_reports.id')
                    ->where('loot_entries.item_id', $itemId),
                'farmed'
            )
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->whereNotIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'RETURN', 'WAREHOUSE_CRAFT_CONSUME'])
            ->whereExists(function ($q) use ($itemId) {
                $q->select(DB::raw(1))
                    ->from('loot_entries')
                    ->whereColumn('loot_entries.loot_report_id', 'loot_reports.id')
                    ->where('loot_entries.item_id', $itemId);
            })
            ->orderByDesc('loot_reports.id')
            ->with('requestedBy:id,name', 'attendees')
            ->get();

        // Pre-compute sold-against-source per source_report_id by walking the
        // audit log. AuditLog.new_values is a JSON column.
        $soldBySource = collect(
            DB::table('audit_logs')
                ->where('action', 'WAREHOUSE_SELL')
                ->get(['new_values'])
        )->reduce(function ($acc, $row) use ($itemId) {
            $payload = is_string($row->new_values) ? json_decode($row->new_values, true) : (array) $row->new_values;
            if (!is_array($payload)) {
                return $acc;
            }
            if ((int) ($payload['item_id'] ?? 0) !== $itemId) {
                return $acc;
            }
            $srcId = (int) ($payload['source_report_id'] ?? 0);
            if ($srcId === 0) {
                return $acc;
            }
            $acc[$srcId] = ($acc[$srcId] ?? 0) + (int) ($payload['amount'] ?? 0);
            return $acc;
        }, []);

        $candidates = $farms->map(function ($r) use ($soldBySource) {
            $farmed = (int) ($r->farmed ?? 0);
            $sold = (int) ($soldBySource[$r->id] ?? 0);
            $pending = max(0, $farmed - $sold);

            return [
                'id' => $r->id,
                'event_type' => $r->event_type,
                'requested_by' => $r->requestedBy?->name,
                'created_at' => $r->created_at?->toIso8601String(),
                'cp_share_pct' => (int) ($r->cp_share_pct ?? 0),
                'farmed' => $farmed,
                'sold' => $sold,
                'pending' => $pending,
                'attendees' => $r->attendees->map(fn ($a) => [
                    'id' => $a->id,
                    'user_id' => $a->user_id,
                    'name' => $a->is_external ? $a->external_name : ($a->user?->name ?? null),
                    'is_external' => (bool) $a->is_external,
                ])->values(),
            ];
        })->filter(fn ($r) => $r['pending'] > 0)->values();

        return response()->json([
            'item_id' => $itemId,
            'candidates' => $candidates,
        ]);
    }

    public function requestReturn(Request $request)
    {
        $request->validate([
            'item_id' => 'required|exists:items,id',
            'amount' => 'required|integer|min:1',
            'image_proof' => 'required|image|max:4096',
        ]);

        $member = $request->user();
        if (! $member->cp_id) {
            abort(403);
        }

        $item = Item::findOrFail($request->item_id);
        if (strtolower($item->name) === 'adena') {
            return back()->withErrors(['item_id' => 'La Adena se gestiona como saldo, no como ítem.']);
        }

        $assigned = LootEntry::query()
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->where('loot_reports.cp_id', $member->cp_id)
            ->where('loot_reports.status', 'confirmed')
            ->where('loot_reports.event_type', 'ASSIGN')
            ->where('loot_entries.awarded_to', $member->id)
            ->where('loot_entries.item_id', $request->item_id)
            ->sum('loot_entries.amount');

        $returned = LootEntry::query()
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->where('loot_reports.cp_id', $member->cp_id)
            ->where('loot_reports.status', 'confirmed')
            ->where('loot_reports.event_type', 'RETURN')
            ->where('loot_entries.awarded_to', $member->id)
            ->where('loot_entries.item_id', $request->item_id)
            ->sum('loot_entries.amount');

        $available = max(0, (int) $assigned - (int) $returned);
        if ($available < $request->amount) {
            return back()->withErrors(['amount' => 'No tienes suficiente cantidad asignada para devolver. Disponible: '.$available]);
        }

        DB::transaction(function () use ($request, $member) {
            $report = LootReport::create([
                'cp_id' => $member->cp_id,
                'requested_by_id' => $member->id,
                'event_type' => 'RETURN',
                'status' => 'pending',
                'image_proof' => null,
                'recipient_ids' => [$member->id],
            ]);

            $file = $request->file('image_proof');
            $ext = $file->extension() ?: ($file->guessExtension() ?: 'jpg');
            $imagePath = $file->storeAs("returns/{$member->cp_id}", "{$report->id}.{$ext}", 'public');
            $report->image_proof = $imagePath;
            $report->save();

            LootEntry::create([
                'loot_report_id' => $report->id,
                'item_id' => $request->item_id,
                'awarded_to' => $member->id,
                'amount' => $request->amount,
            ]);

            $item = Item::find($request->item_id);
            $audit = AuditLog::create([
                'entity_type' => 'LootReport',
                'entity_id' => $report->id,
                'user_id' => $member->id,
                'action' => 'WAREHOUSE_RETURN_REQUEST',
                'old_values' => null,
                'new_values' => [
                    'item_id' => (int) $request->item_id,
                    'item_name' => $item?->name,
                    'amount' => (int) $request->amount,
                ],
            ]);
            $recipients = collect([$member->id]);
            $leaderId = optional($member->cp)->leader_id;
            if ($leaderId) {
                $recipients->push($leaderId);
            }
            $recipients = $recipients->unique()->values();
            $amountLabel = 'x'.number_format((int) $request->amount, 0, ',', '.');
            $summary = "{$member->name} solicitó devolver {$item?->name} {$amountLabel} a la CP";
            $now = now();
            $rows = $recipients->map(fn ($rid) => [
                'audit_log_id' => $audit->id,
                'recipient_user_id' => $rid,
                'actor_user_id' => $member->id,
                'entity_type' => 'LootReport',
                'entity_id' => $report->id,
                'action' => 'WAREHOUSE_RETURN_REQUEST',
                'summary' => $summary,
                'meta' => json_encode(['report_id' => $report->id, 'item_id' => (int) $request->item_id]),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            DB::table('audit_alerts')->insert($rows);
        });

        return back()->with('success', 'Solicitud de devolución creada. Un líder deberá aceptarla o rechazarla.');
    }

    public function addStock(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.amount' => 'required|integer|min:1',
            'image_proof' => 'required|image|max:4096',
        ]);

        $current = $request->user();
        $roleName = $current->role?->name;
        if (! in_array($roleName, ['admin', 'cp_leader', 'accountant'], true)) {
            abort(403, 'No tienes permiso para añadir stock al warehouse.');
        }
        if (! $current->cp_id) {
            abort(403);
        }

        DB::transaction(function () use ($request, $current) {
            $report = LootReport::create([
                'cp_id' => $current->cp_id,
                'requested_by_id' => $current->id,
                'event_type' => 'WAREHOUSE_ADD',
                'status' => 'confirmed',
                'image_proof' => null,
            ]);

            $file = $request->file('image_proof');
            $ext = $file->extension() ?: ($file->guessExtension() ?: 'jpg');
            $imagePath = $file->storeAs("warehouse_add/{$current->cp_id}", "{$report->id}.{$ext}", 'public');
            $report->image_proof = $imagePath;
            $report->save();

            foreach ($request->items as $itemData) {
                LootEntry::create([
                    'loot_report_id' => $report->id,
                    'item_id' => $itemData['item_id'],
                    'amount' => $itemData['amount'],
                ]);
            }

            $audit = AuditLog::create([
                'entity_type' => 'LootReport',
                'entity_id' => $report->id,
                'user_id' => $current->id,
                'action' => 'WAREHOUSE_ADD',
                'old_values' => null,
                'new_values' => [
                    'items' => collect($request->items)->map(fn ($i) => ['item_id' => (int) $i['item_id'], 'amount' => (int) $i['amount']])->all(),
                ],
            ]);
            $recipients = collect([$current->id]);
            $leaderId = optional($current->cp)->leader_id;
            if ($leaderId) {
                $recipients->push($leaderId);
            }
            $recipients = $recipients->unique()->values();
            $summary = "{$current->name} añadió stock al warehouse (Reporte #{$report->id})";
            $now = now();
            $rows = $recipients->map(fn ($rid) => [
                'audit_log_id' => $audit->id,
                'recipient_user_id' => $rid,
                'actor_user_id' => $current->id,
                'entity_type' => 'LootReport',
                'entity_id' => $report->id,
                'action' => 'WAREHOUSE_ADD',
                'summary' => $summary,
                'meta' => json_encode(['report_id' => $report->id]),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            DB::table('audit_alerts')->insert($rows);
        });

        return back()->with('success', 'Stock añadido al warehouse y registrado.');
    }

    public function buyStock(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.amount' => 'required|integer|min:1',
            'adena_spent' => 'required|integer|min:1',
            'description' => 'nullable|string|max:5000',
            'image_proof' => 'nullable|image|max:4096',
        ]);

        $current = $request->user();
        $roleName = $current->role?->name;
        if (! in_array($roleName, ['admin', 'cp_leader', 'accountant'], true)) {
            abort(403, 'No tienes permiso para registrar compras del warehouse.');
        }
        if (! $current->cp_id) {
            abort(403);
        }

        $cpId = (int) $current->cp_id;
        $adenaSpent = abs((int) $request->adena_spent);

        $itemIds = collect($request->items)->pluck('item_id')->map(fn ($v) => (int) $v)->filter(fn ($v) => $v > 0)->values();
        $containsAdena = Item::query()
            ->whereIn('id', $itemIds)
            ->whereRaw('LOWER(name) = ?', ['adena'])
            ->exists();
        if ($containsAdena) {
            return back()->withErrors(['items' => 'La Adena se gestiona en el campo de Adena gastada.']);
        }

        $adenaItem = Item::whereRaw('LOWER(name) = ?', ['adena'])->first();
        if (! $adenaItem) {
            return back()->withErrors(['adena_spent' => 'No existe el ítem Adena en la base de datos.']);
        }

        DB::transaction(function () use ($request, $current, $cpId, $adenaSpent, $adenaItem) {
            $report = LootReport::create([
                'cp_id' => $cpId,
                'requested_by_id' => $current->id,
                'event_type' => 'WAREHOUSE_BUY',
                'status' => 'confirmed',
                'image_proof' => null,
                'description' => $request->input('description'),
            ]);

            $file = $request->file('image_proof');
            if ($file) {
                $ext = $file->extension() ?: ($file->guessExtension() ?: 'jpg');
                $imagePath = $file->storeAs("warehouse_buy/{$cpId}", "{$report->id}.{$ext}", 'public');
                $report->image_proof = $imagePath;
                $report->save();
            }

            foreach ($request->items as $itemData) {
                LootEntry::create([
                    'loot_report_id' => $report->id,
                    'item_id' => $itemData['item_id'],
                    'amount' => $itemData['amount'],
                ]);
            }

            LootEntry::create([
                'loot_report_id' => $report->id,
                'item_id' => $adenaItem->id,
                'amount' => -$adenaSpent,
            ]);

            $audit = AuditLog::create([
                'entity_type' => 'LootReport',
                'entity_id' => $report->id,
                'user_id' => $current->id,
                'action' => 'WAREHOUSE_BUY',
                'old_values' => null,
                'new_values' => [
                    'items' => collect($request->items)->map(fn ($i) => ['item_id' => (int) $i['item_id'], 'amount' => (int) $i['amount']])->all(),
                    'adena_spent' => $adenaSpent,
                    'description' => $request->input('description'),
                ],
            ]);
            $recipients = collect([$current->id]);
            $leaderId = optional($current->cp)->leader_id;
            if ($leaderId) {
                $recipients->push($leaderId);
            }
            $recipients = $recipients->unique()->values();
            $totalLabel = number_format((int) $adenaSpent, 0, ',', '.');
            $summary = "{$current->name} registró una compra por {$totalLabel} Adena (Reporte #{$report->id})";
            $now = now();
            $rows = $recipients->map(fn ($rid) => [
                'audit_log_id' => $audit->id,
                'recipient_user_id' => $rid,
                'actor_user_id' => $current->id,
                'entity_type' => 'LootReport',
                'entity_id' => $report->id,
                'action' => 'WAREHOUSE_BUY',
                'summary' => $summary,
                'meta' => json_encode(['report_id' => $report->id]),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            DB::table('audit_alerts')->insert($rows);
        });

        return back()->with('success', 'Compra registrada. Adena descontada del warehouse.');
    }

    private function craftableRecipeIdByItemId(string $chronicle): array
    {
        return Item::craftableRecipeIdByItemId($chronicle);
    }
}

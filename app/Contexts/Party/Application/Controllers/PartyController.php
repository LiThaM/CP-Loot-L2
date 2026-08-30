<?php

namespace App\Contexts\Party\Application\Controllers;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Application\Controllers\CraftingController;
use App\Contexts\Loot\Domain\Models\CpEventConfig;
use App\Contexts\Loot\Domain\Models\CpRecipe;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\LootEntry;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Loot\Domain\Models\LootReportAttendee;
use App\Contexts\Loot\Domain\Models\Recipe;
use App\Contexts\Loot\Domain\Services\CraftedPriceService;
use App\Contexts\Party\Application\Services\TrackerContributionService;
use App\Contexts\Party\Domain\Models\ConstParty;
use App\Contexts\Party\Domain\Models\PointsLog;
use App\Contexts\System\Domain\Models\AuditLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
                'items.category',
                'items.market_price',
                'items.market_price_updated_at',
                'items.market_price_updated_by',
                'items.npc_sell_price',
                DB::raw('SUM(loot_entries.amount) as incoming_amount'),
                DB::raw('MAX(loot_reports.created_at) as last_added_at'),
            ])
            ->join('items', 'items.id', '=', 'loot_entries.item_id')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->where('loot_reports.cp_id', $user->cp_id)
            ->where('loot_reports.status', 'confirmed')
            ->whereNull('loot_reports.voided_at')
            ->whereNotIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME', 'WAREHOUSE_RECHECK_LOSS'])
            ->whereRaw('LOWER(items.name) != ?', ['adena'])
            ->groupBy('items.id', 'items.name', 'items.icon_name', 'items.image_url', 'items.grade', 'items.category', 'items.market_price', 'items.market_price_updated_at', 'items.market_price_updated_by', 'items.npc_sell_price')
            ->get()
            ->keyBy('id');

        $priceEditorIds = $warehouseIncoming->pluck('market_price_updated_by')->filter()->unique()->all();
        $priceEditorNames = ! empty($priceEditorIds)
            ? DB::table('users')->whereIn('id', $priceEditorIds)->pluck('name', 'id')
            : collect();

        $warehouseOutgoing = LootEntry::query()
            ->select([
                'items.id',
                DB::raw('SUM(loot_entries.amount) as outgoing_amount'),
            ])
            ->join('items', 'items.id', '=', 'loot_entries.item_id')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->where('loot_reports.cp_id', $user->cp_id)
            ->where('loot_reports.status', 'confirmed')
            ->whereNull('loot_reports.voided_at')
            ->whereIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME', 'WAREHOUSE_RECHECK_LOSS'])
            ->whereRaw('LOWER(items.name) != ?', ['adena'])
            ->groupBy('items.id')
            ->pluck('outgoing_amount', 'id');

        // L2 grade tiers: S > A > B > C > D > NG. Items without a grade go last.
        $gradeRank = ['S' => 6, 'A' => 5, 'B' => 4, 'C' => 3, 'D' => 2, 'NG' => 1];

        // Computed "craft cost" per item (Σ material market prices), shown as a
        // second price next to the manual one and used to value crafted items
        // that nobody priced by hand yet.
        $craftedPrices = app(CraftedPriceService::class)->mapForChronicle((string) ($cp->chronicle ?: 'IL'));

        // Per-unit tracker points, shown next to the market price for CPs that
        // run the value tracker. Mirrors TrackerContributionService valuation:
        // effective price (market→NPC per the CP's basis) ÷ divisor, rounded the
        // same way the CP's tracker does (ceil when "whole points" is on, else 2
        // decimals). No 1000-floor (that's a per-stack rule) and no craft cost.
        $trackerOn = (bool) $cp->tracker_enabled;
        $trackerDivisor = max(1, (int) $cp->tracker_divisor);
        $valueByMarket = (bool) ($cp->tracker_value_by_market ?? true);
        $wholePoints = (bool) ($cp->tracker_round_points_up ?? false);

        $warehouseItems = $warehouseIncoming->map(function ($row) use ($warehouseOutgoing, $priceEditorNames, $gradeRank, $craftedPrices, $trackerOn, $trackerDivisor, $valueByMarket, $wholePoints) {
            $out = (int) ($warehouseOutgoing[$row->id] ?? 0);
            $in = (int) ($row->incoming_amount ?? 0);
            $row->total_amount = max(0, $in - $out);
            $row->market_price = $row->market_price !== null ? (int) $row->market_price : null;
            $row->npc_sell_price = $row->npc_sell_price !== null ? (int) $row->npc_sell_price : null;
            $row->crafted_price = $craftedPrices[$row->id] ?? null;
            // Stock value uses the effective price: user-set market_price wins,
            // then the computed craft cost, with npc_sell_price as the base floor.
            $effectivePrice = $row->market_price ?? $row->crafted_price ?? $row->npc_sell_price;
            $row->stock_value = $effectivePrice !== null ? $effectivePrice * $row->total_amount : null;

            $row->points_per_unit = null;
            if ($trackerOn) {
                $trackerPrice = $valueByMarket
                    ? ($row->market_price ?? $row->npc_sell_price)
                    : ($row->npc_sell_price ?? $row->market_price);
                if ($trackerPrice !== null && $trackerPrice > 0) {
                    $pts = $trackerPrice / $trackerDivisor;
                    $row->points_per_unit = $wholePoints ? (float) ceil($pts) : round($pts, 2);
                }
            }
            $row->market_price_updated_by_name = $row->market_price_updated_by ? ($priceEditorNames[$row->market_price_updated_by] ?? null) : null;
            $row->grade_rank = $gradeRank[$row->grade] ?? 0;
            unset($row->incoming_amount, $row->market_price_updated_by);

            return $row;
        })->values()
            ->filter(fn ($row) => (int) $row->total_amount > 0)
            ->sortBy([
                ['grade_rank', 'desc'],
                ['total_amount', 'desc'],
                ['last_added_at', 'desc'],
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
                        'market_price' => $mat->item?->market_price !== null ? (int) $mat->item->market_price : null,
                        'npc_sell_price' => $mat->item?->npc_sell_price !== null ? (int) $mat->item->npc_sell_price : null,
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

                // Determine the primary output category to decide whether
                // a recipe scroll is consumable (Weapon/Armor/Jewelry) or
                // not (Material/EtcItem/Recipe — intermediate craftables).
                $primaryOutputItem = $recipe?->outputItem
                    ?? ($outputs->first()?->item);
                $primaryOutputId = (int) ($primaryOutputItem?->id ?? 0);
                $requiresScroll = $recipe && $primaryOutputId
                    ? CraftingController::requiresRecipeScroll($recipe, $primaryOutputId)
                    : false;

                if ($recipe?->recipe_item_id && $requiresScroll) {
                    $recipeItem = $recipe->recipeItem;
                    $have = (int) ($warehouseAmountsByItemId[$recipe->recipe_item_id] ?? 0);
                    $materialsList->prepend([
                        'item_id' => $recipe->recipe_item_id,
                        'name' => $recipeItem?->name ?? 'Receta '.$recipe->name,
                        'image_url' => $recipeItem?->image_url,
                        'need' => 1,
                        'have' => $have,
                        'missing' => max(0, 1 - $have),
                        'craft_recipe_id' => null,
                        'craftable' => false,
                        'is_recipe' => true,
                    ]);
                }

                // pluck() returns a Collection; simulate() expects arrays.
                $autoPlan = $recipe ? CraftingController::simulate(
                    $recipe,
                    $warehouseAmountsByItemId instanceof Collection ? $warehouseAmountsByItemId->all() : (array) $warehouseAmountsByItemId,
                    is_array($craftableRecipeIdByItemId) ? $craftableRecipeIdByItemId : (array) $craftableRecipeIdByItemId,
                ) : null;
                $hydratedAuto = null;
                $maxCraftable = 0;
                if ($autoPlan) {
                    $itemIds = array_unique(array_merge(array_keys($autoPlan['auto_crafted']), array_keys($autoPlan['consumed'])));
                    $byId = ! empty($itemIds)
                        ? Item::whereIn('id', $itemIds)->get(['id', 'name', 'image_url'])->keyBy('id')
                        : collect();
                    $hydrate = fn ($map) => collect($map)->map(fn ($qty, $id) => [
                        'item_id' => (int) $id,
                        'name' => $byId[$id]?->name,
                        'image_url' => $byId[$id]?->image_url,
                        'amount' => (int) $qty,
                    ])->values();
                    $hydratedAuto = [
                        'auto_crafted' => $hydrate($autoPlan['auto_crafted']),
                        'consumed' => $hydrate($autoPlan['consumed']),
                    ];

                    // max_craftable = min(floor(have / consumed_per_craft)) for each consumed item
                    $warehouseAll = $warehouseAmountsByItemId instanceof Collection
                        ? $warehouseAmountsByItemId->all()
                        : (array) $warehouseAmountsByItemId;
                    $maxCraftable = PHP_INT_MAX;
                    foreach ($autoPlan['consumed'] as $itemId => $amountPerCraft) {
                        if ($amountPerCraft <= 0) {
                            continue;
                        }
                        $have = (int) ($warehouseAll[$itemId] ?? 0);
                        $maxCraftable = min($maxCraftable, intdiv($have, $amountPerCraft));
                    }
                    // Also cap by recipe scroll availability (simulate() doesn't consume it)
                    if ($requiresScroll && $recipe->recipe_item_id) {
                        $haveScroll = (int) ($warehouseAll[$recipe->recipe_item_id] ?? 0);
                        $maxCraftable = min($maxCraftable, $haveScroll);
                    }
                    $maxCraftable = $maxCraftable === PHP_INT_MAX ? 0 : max(0, $maxCraftable);
                }

                return [
                    'id' => $cpRecipe->id,
                    'priority' => $cpRecipe->priority,
                    'recipe' => $recipe ? [
                        'id' => $recipe->id,
                        'name' => $recipe->name,
                        'success_rate' => $recipe->success_rate,
                        'requires_recipe_scroll' => $requiresScroll,
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
                        'auto_craft_plan' => $hydratedAuto,
                        'max_craftable' => $maxCraftable,
                    ] : null,
                ];
            })
            ->values();

        $adenaIn = LootEntry::query()
            ->join('items', 'items.id', '=', 'loot_entries.item_id')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->where('loot_reports.cp_id', $user->cp_id)
            ->where('loot_reports.status', 'confirmed')
            ->whereNull('loot_reports.voided_at')
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
        $initialTab = in_array($tab, ['members', 'warehouse_cp', 'crafting', 'config', 'settings'], true) ? $tab : 'members';

        $warehouseStockValue = $warehouseItems->sum(fn ($r) => (int) ($r->stock_value ?? 0));
        $warehouseStockPriced = $warehouseItems->filter(fn ($r) => $r->market_price !== null)->count();

        $isLeader = $user->id === $cp->leader_id;
        $isAdmin = $user->role?->name === 'admin';
        // With the CP's staff_can_manage_members opt-in, co-leaders and
        // accountants can see the invite code and approve pending members.
        // Regenerating the code stays founder/admin-only.
        $canManageMembers = $isLeader || $isAdmin
            || ((bool) $cp->staff_can_manage_members && in_array($user->role?->name, ['cp_leader', 'accountant'], true));
        $canRegenerateInvite = $isLeader || $isAdmin;

        return Inertia::render('Party/Index', [
            'has_cp' => true,
            'cp' => $cp,
            'members' => $members,
            'eventConfigs' => $eventConfigs,
            'warehouseItems' => $warehouseItems,
            'warehouseStockValue' => $warehouseStockValue,
            'warehouseStockPriced' => $warehouseStockPriced,
            'warehouseAdena' => $warehouseAdena,
            'warehouseAdenaNet' => $warehouseAdena - $cpAdenaOwed,
            'cpAdenaOwed' => $cpAdenaOwed,
            'cpAdenaPaid' => $cpAdenaPaid,
            'cpRecipes' => $cpRecipes,
            'canManageWarehouse' => $canManageWarehouse,
            'isLeader' => $isLeader,
            'canManageMembers' => $canManageMembers,
            'canRegenerateInvite' => $canRegenerateInvite,
            // invite_code is $hidden on the model — this prop is the only
            // way the code reaches the browser, and only for authorized users.
            'inviteCode' => $canManageMembers ? $cp->invite_code : null,
            'initialTab' => $initialTab,
            // Non-admins must not see `admin` in the role dropdown of the
            // user-edit modal. The backend already rejects the assignment
            // in UserManagementController::update, but until now the
            // dropdown still listed it because this payload sent
            // Role::all() unconditionally.
            'roles' => $isAdmin
                ? Role::all()
                : Role::query()->whereIn('name', ['cp_leader', 'accountant', 'member'])->get(),
            'cps' => $isAdmin ? ConstParty::all() : [],
            'isAdmin' => $isAdmin,
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
        // Founder opt-in (staff_can_manage_members): co-leaders and
        // accountants of the same CP may approve pending members too.
        $isAuthorizedStaff = (bool) ($actor->cp?->staff_can_manage_members)
            && in_array($actor->role?->name, ['cp_leader', 'accountant'], true);

        if (! $isLeader && ! $isAdmin && ! $isAuthorizedStaff) {
            abort(403);
        }

        $oldStatus = $user->membership_status;
        $user->update(['membership_status' => 'approved']);

        AuditLog::create([
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'user_id' => $actor->id,
            'action' => 'USER_APPROVED',
            'old_values' => ['membership_status' => $oldStatus],
            'new_values' => ['membership_status' => 'approved'],
        ]);

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
            ->whereNull('loot_reports.voided_at')
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
            ->whereNull('loot_reports.voided_at')
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

        // Even an admin needs the target user to still belong to a CP — a
        // dangling user with cp_id = null would otherwise spill warehouse
        // logs scoped to NULL across the response.
        if (! $user->cp_id) {
            abort(404);
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
            ->whereNull('loot_reports.voided_at')
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
            ->whereNull('loot_reports.voided_at')
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

        // Items contributed: loot from FARM sessions the member attended
        $farmReportIds = DB::table('loot_report_attendees')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_report_attendees.loot_report_id')
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->whereNull('loot_reports.voided_at')
            ->where('loot_reports.event_type', 'FARM')
            ->where('loot_report_attendees.user_id', $user->id)
            ->pluck('loot_reports.id');

        $contributed = LootEntry::query()
            ->select([
                'items.id',
                'items.name',
                'items.icon_name',
                'items.image_url',
                'items.grade',
                DB::raw('SUM(loot_entries.amount) as total_amount'),
                DB::raw('MAX(loot_reports.created_at) as last_added_at'),
            ])
            ->join('items', 'items.id', '=', 'loot_entries.item_id')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->whereIn('loot_entries.loot_report_id', $farmReportIds)
            ->whereRaw('LOWER(items.name) != ?', ['adena'])
            ->groupBy('items.id', 'items.name', 'items.icon_name', 'items.image_url', 'items.grade')
            ->orderByDesc('last_added_at')
            ->get()
            ->values();

        return response()->json([
            'user_id' => $user->id,
            'items' => $items,
            'contributed' => $contributed,
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
            'image_proof' => $this->imageProofRule($request->user()),
            'adena_offset' => 'nullable|integer|min:0',
            // When checked, the leader explicitly skips the DKP cost
            // (gift / event reward / etc.). Default behaviour when the
            // CP has the value-based tracker enabled is to deduct points
            // from the receiver based on `item value / divisor`.
            'skip_dkp_cost' => 'nullable|boolean',
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
                    ->whereNull('loot_reports.voided_at')
                    ->whereNotIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME', 'WAREHOUSE_RECHECK_LOSS'])
                    ->where('loot_entries.item_id', $request->item_id)
                    ->sum('loot_entries.amount');

                $outgoing = LootEntry::query()
                    ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
                    ->where('loot_reports.cp_id', $cpId)
                    ->where('loot_reports.status', 'confirmed')
                    ->whereNull('loot_reports.voided_at')
                    ->whereIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME', 'WAREHOUSE_RECHECK_LOSS'])
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
                if ($file) {
                    $ext = $file->extension() ?: ($file->guessExtension() ?: 'jpg');
                    $imagePath = $file->storeAs("transfers/{$cpId}", "{$report->id}.{$ext}", 'public');
                    $report->image_proof = $imagePath;
                    $report->save();
                }

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

        // DKP cost on assign — best-effort, can never roll back the
        // assignment itself. Only runs when (a) the CP has the value-based
        // tracker on and (b) the leader didn't explicitly opt out via the
        // "Gift / don't deduct" checkbox.
        if (! $request->boolean('skip_dkp_cost', false)) {
            $cp = ConstParty::find($cpId);
            if ($cp && $cp->tracker_enabled) {
                try {
                    $latestReport = LootReport::where('cp_id', $cpId)
                        ->where('event_type', 'ASSIGN')
                        ->where('requested_by_id', $current->id)
                        ->latest('id')
                        ->first();
                    if ($latestReport) {
                        app(TrackerContributionService::class)
                            ->recordAssignmentCost($latestReport->load('cp', 'entries.item'));
                    }
                } catch (\Throwable $e) {
                    Log::warning('Assign DKP cost recording failed', [
                        'cp_id' => $cpId, 'error' => $e->getMessage(),
                    ]);
                }
            }
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
            'image_proof' => $this->imageProofRule($request->user()),
        ]);

        $current = $request->user();
        if (! $this->canManageWarehouse($current)) {
            abort(403, 'No tienes permiso para vender ítems del warehouse.');
        }

        $cpId = $current->cp_id;
        $item = Item::findOrFail($request->item_id);
        if (strtolower($item->name) === 'adena') {
            return back()->withErrors(['item_id' => 'La Adena se gestiona como saldo, no se vende como ítem del warehouse.']);
        }

        $sourceReport = LootReport::with('attendees')->find($request->source_report_id);
        if (! $sourceReport || $sourceReport->cp_id !== $cpId || $sourceReport->status !== 'confirmed') {
            return back()->withErrors(['source_report_id' => 'La sesión de farm seleccionada no es válida para esta CP.']);
        }
        $sourceHasItem = LootEntry::where('loot_report_id', $sourceReport->id)
            ->where('item_id', $item->id)
            ->exists();
        if (! $sourceHasItem) {
            return back()->withErrors(['source_report_id' => 'Esa sesión de farm no contiene este ítem.']);
        }

        $amount = (int) $request->amount;
        $unitPrice = (int) $request->unit_price;
        $cpSharePct = (int) $request->cp_share_pct;

        $available = $this->currentStock($cpId, $item->id);
        if ($available < $amount) {
            return back()->withErrors(['amount' => 'Stock insuficiente en el warehouse. Disponible: '.$available]);
        }

        if ($sourceReport->attendees->isEmpty() && $cpSharePct < 100) {
            return back()->withErrors(['source_report_id' => 'La sesión origen no tiene attendees registrados; o márcala con 100% al CP fund o resuelve la sesión añadiendo attendees.']);
        }

        $imagePath = $this->storeSellImage($request->file('image_proof'), $cpId);

        try {
            $result = DB::transaction(function () use ($cpId, $current, $item, $amount, $unitPrice, $cpSharePct, $sourceReport, $imagePath) {
                Item::whereKey($item->id)->lockForUpdate()->first();
                $stock = $this->currentStock($cpId, $item->id);
                if ($stock < $amount) {
                    throw new \RuntimeException('INSUFFICIENT_STOCK:'.$stock);
                }

                return $this->createSellReportForSource($cpId, $sourceReport, $item, $amount, $unitPrice, $cpSharePct, $imagePath, $current);
            });
        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'INSUFFICIENT_STOCK:')) {
                $available = (int) substr($e->getMessage(), strlen('INSUFFICIENT_STOCK:'));

                return back()->withErrors(['amount' => 'Stock insuficiente en el warehouse. Disponible: '.$available]);
            }
            throw $e;
        }

        $totalLabel = number_format((int) $result['total_adena'], 0, ',', '.');
        $summary = "{$current->name} vendió {$item->name} x{$amount} por {$totalLabel} Adena (origen #{$sourceReport->id})";
        $this->emitSellAlerts($current, [$result], $item, $summary);

        return back()->with('success', 'Venta registrada. Adena añadida al warehouse.');
    }

    public function sellAuto(Request $request)
    {
        $request->validate([
            'item_id' => 'required|exists:items,id',
            'total_amount' => 'required|integer|min:1',
            'unit_price' => 'required|integer|min:1',
            'allocations' => 'required|array|min:1',
            'allocations.*.source_report_id' => 'required|integer|exists:loot_reports,id',
            'allocations.*.amount' => 'required|integer|min:1',
            'image_proof' => $this->imageProofRule($request->user()),
        ]);

        $current = $request->user();
        if (! $this->canManageWarehouse($current)) {
            abort(403, 'No tienes permiso para vender ítems del warehouse.');
        }

        $cpId = $current->cp_id;
        $item = Item::findOrFail($request->item_id);
        if (strtolower($item->name) === 'adena') {
            return back()->withErrors(['item_id' => 'La Adena se gestiona como saldo, no se vende como ítem del warehouse.']);
        }

        $totalAmount = (int) $request->total_amount;
        $unitPrice = (int) $request->unit_price;
        $allocations = collect($request->input('allocations'))->map(fn ($a) => [
            'source_report_id' => (int) $a['source_report_id'],
            'amount' => (int) $a['amount'],
        ])->values();

        if ($allocations->sum('amount') !== $totalAmount) {
            return back()->withErrors(['allocations' => 'La suma de las allocaciones no coincide con total_amount.']);
        }

        $sourceReports = LootReport::with('attendees')
            ->whereIn('id', $allocations->pluck('source_report_id'))
            ->where('cp_id', $cpId)
            ->where('status', 'confirmed')
            ->get()
            ->keyBy('id');

        foreach ($allocations as $alloc) {
            $src = $sourceReports[$alloc['source_report_id']] ?? null;
            if (! $src) {
                return back()->withErrors(['allocations' => 'Una de las sesiones de farm no es válida para esta CP.']);
            }
            $hasItem = LootEntry::where('loot_report_id', $src->id)->where('item_id', $item->id)->exists();
            if (! $hasItem) {
                return back()->withErrors(['allocations' => "El farm #{$src->id} no contiene este ítem."]);
            }
            $pending = $this->pendingFromSource($src->id, $item->id);
            if ($alloc['amount'] > $pending) {
                return back()->withErrors(['allocations' => "El farm #{$src->id} sólo tiene {$pending} disponibles (pediste {$alloc['amount']})."]);
            }
            if ($src->attendees->isEmpty() && (int) $src->cp_share_pct < 100) {
                return back()->withErrors(['allocations' => "El farm #{$src->id} no tiene attendees; véndelo por separado con CP 100%."]);
            }
        }

        $available = $this->currentStock($cpId, $item->id);
        if ($available < $totalAmount) {
            return back()->withErrors(['total_amount' => 'Stock insuficiente en el warehouse. Disponible: '.$available]);
        }

        $batchId = (string) Str::uuid();
        $imagePath = $this->storeSellImage($request->file('image_proof'), $cpId, $batchId);

        try {
            $results = DB::transaction(function () use ($cpId, $current, $item, $allocations, $sourceReports, $unitPrice, $imagePath, $batchId, $totalAmount) {
                Item::whereKey($item->id)->lockForUpdate()->first();
                $stock = $this->currentStock($cpId, $item->id);
                if ($stock < $totalAmount) {
                    throw new \RuntimeException('INSUFFICIENT_STOCK:'.$stock);
                }
                $out = [];
                foreach ($allocations as $alloc) {
                    $src = $sourceReports[$alloc['source_report_id']];
                    $out[] = $this->createSellReportForSource(
                        $cpId, $src, $item, $alloc['amount'], $unitPrice, (int) $src->cp_share_pct,
                        $imagePath, $current, $batchId,
                    );
                }

                return $out;
            });
        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'INSUFFICIENT_STOCK:')) {
                $available = (int) substr($e->getMessage(), strlen('INSUFFICIENT_STOCK:'));

                return back()->withErrors(['total_amount' => 'Stock insuficiente en el warehouse. Disponible: '.$available]);
            }
            throw $e;
        }

        $totalAdena = collect($results)->sum('total_adena');
        $totalLabel = number_format((int) $totalAdena, 0, ',', '.');
        $n = count($results);
        $summary = "{$current->name} vendió {$item->name} x{$totalAmount} por {$totalLabel} Adena ({$n} ventas auto)";
        $this->emitSellAlerts($current, $results, $item, $summary);

        return back()->with('success', "Venta registrada en {$n} reports.");
    }

    // If the CP toggled image_proof_required off, image_proof becomes
    // optional in every action that used to demand it (add/buy/sell/recheck).
    private function imageProofRule(?User $user): string
    {
        $required = $user?->cp?->image_proof_required ?? true;

        return ($required ? 'required' : 'nullable').'|image|max:4096';
    }

    private function canManageWarehouse(?User $user): bool
    {
        if (! $user || ! $user->cp_id) {
            return false;
        }
        $role = $user->role?->name;

        return in_array($role, ['admin', 'cp_leader', 'accountant'], true);
    }

    private function currentStock(int $cpId, int $itemId): int
    {
        $incoming = LootEntry::query()
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->whereNull('loot_reports.voided_at')
            ->whereNotIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME', 'WAREHOUSE_RECHECK_LOSS'])
            ->where('loot_entries.item_id', $itemId)
            ->sum('loot_entries.amount');

        $outgoing = LootEntry::query()
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->whereNull('loot_reports.voided_at')
            ->whereIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME', 'WAREHOUSE_RECHECK_LOSS'])
            ->where('loot_entries.item_id', $itemId)
            ->sum('loot_entries.amount');

        return max(0, (int) $incoming - (int) $outgoing);
    }

    // How many units of `$itemId` from this single source farm are still
    // in the vault (i.e. not yet sold via a SELL referencing this farm in
    // its audit_log new_values). Walks audit logs in PHP rather than
    // relying on whereJsonContains — same pattern as sellSourceCandidates,
    // which is the authoritative source for "pending" math.
    private function pendingFromSource(int $sourceReportId, int $itemId): int
    {
        $farmed = (int) LootEntry::where('loot_report_id', $sourceReportId)
            ->where('item_id', $itemId)
            ->sum('amount');

        $sold = DB::table('audit_logs')
            ->where('action', 'WAREHOUSE_SELL')
            ->get(['new_values'])
            ->reduce(function ($acc, $row) use ($sourceReportId, $itemId) {
                $payload = is_string($row->new_values) ? json_decode($row->new_values, true) : (array) $row->new_values;
                if (! is_array($payload)) {
                    return $acc;
                }
                if ((int) ($payload['item_id'] ?? 0) !== $itemId) {
                    return $acc;
                }
                if ((int) ($payload['source_report_id'] ?? 0) !== $sourceReportId) {
                    return $acc;
                }

                return $acc + (int) ($payload['amount'] ?? 0);
            }, 0);

        return max(0, $farmed - $sold);
    }

    private function storeSellImage($file, int $cpId, ?string $batchId = null): ?string
    {
        if (! $file) {
            return null;
        }
        $ext = $file->extension() ?: ($file->guessExtension() ?: 'jpg');
        $name = $batchId ? "auto_{$batchId}.{$ext}" : Str::uuid().".{$ext}";

        return $file->storeAs("warehouse_sell/{$cpId}", $name, 'public');
    }

    private function createSellReportForSource(
        int $cpId,
        LootReport $sourceReport,
        Item $item,
        int $amount,
        int $unitPrice,
        int $cpSharePct,
        string $imagePath,
        User $actor,
        ?string $batchId = null,
    ): array {
        $sourceAttendees = $sourceReport->attendees;
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

        $report = LootReport::create([
            'cp_id' => $cpId,
            'requested_by_id' => $actor->id,
            'event_type' => 'SELL',
            'status' => 'confirmed',
            'image_proof' => $imagePath,
            'recipient_ids' => ! empty($memberIds) ? $memberIds : null,
            'adena_distribution' => $cpSharePct === 100 ? 'cp' : 'attendees',
            'cp_share_pct' => $cpSharePct,
        ]);

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
                'character_id' => $att->character_id,
                'external_name' => $att->external_name,
                'is_external' => $att->is_external,
                'share_adena' => $perAttendee,
            ]);

            if (! $att->is_external && $att->user_id && $perAttendee > 0) {
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
            'user_id' => $actor->id,
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
                'attendees_count' => $count,
                'external_count' => $sourceAttendees->where('is_external', true)->count(),
                'source_report_id' => $sourceReport->id,
                'auto_allocation_batch_id' => $batchId,
            ],
        ]);

        return [
            'report' => $report,
            'audit_log_id' => $audit->id,
            'source_report_id' => $sourceReport->id,
            'amount' => $amount,
            'total_adena' => $totalAdena,
            'cp_share' => $cpFundFinal,
            'per_attendee' => $perAttendee,
            'attendees_count' => $count,
            'member_ids' => $memberIds,
        ];
    }

    // Insert one audit_alert per unique recipient across all reports in
    // the batch. We anchor every alert to the first report so the user
    // lands on something meaningful when clicking the bell; the summary
    // already communicates "X ventas auto" when relevant.
    private function emitSellAlerts(User $actor, array $results, Item $item, string $summary): void
    {
        if (empty($results)) {
            return;
        }
        $first = $results[0];
        $leaderId = optional($actor->cp)->leader_id;

        $recipients = collect([$actor->id]);
        if ($leaderId) {
            $recipients->push($leaderId);
        }
        foreach ($results as $r) {
            foreach ($r['member_ids'] as $rid) {
                $recipients->push($rid);
            }
        }
        $recipients = $recipients->unique()->values();

        $now = now();
        $reportIds = collect($results)->pluck('report.id')->all();
        $sourceIds = collect($results)->pluck('source_report_id')->all();

        $rows = $recipients->map(fn ($rid) => [
            'audit_log_id' => $first['audit_log_id'],
            'recipient_user_id' => $rid,
            'actor_user_id' => $actor->id,
            'entity_type' => 'LootReport',
            'entity_id' => $first['report']->id,
            'action' => 'WAREHOUSE_SELL',
            'summary' => $summary,
            'meta' => json_encode([
                'report_id' => $first['report']->id,
                'report_ids' => $reportIds,
                'source_report_ids' => $sourceIds,
                'item_id' => (int) $item->id,
            ]),
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        DB::table('audit_alerts')->insert($rows);
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
            ->whereNull('loot_reports.voided_at')
            ->whereNotIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'RETURN', 'WAREHOUSE_CRAFT_CONSUME', 'WAREHOUSE_RECHECK_GAIN', 'WAREHOUSE_RECHECK_LOSS'])
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
            if (! is_array($payload)) {
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
            'image_proof' => $this->imageProofRule($request->user()),
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
            if ($file) {
                $ext = $file->extension() ?: ($file->guessExtension() ?: 'jpg');
                $imagePath = $file->storeAs("returns/{$member->cp_id}", "{$report->id}.{$ext}", 'public');
                $report->image_proof = $imagePath;
                $report->save();
            }

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
            'image_proof' => $this->imageProofRule($request->user()),
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
            if ($file) {
                $ext = $file->extension() ?: ($file->guessExtension() ?: 'jpg');
                $imagePath = $file->storeAs("warehouse_add/{$current->cp_id}", "{$report->id}.{$ext}", 'public');
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

    public function recheck(Request $request)
    {
        $request->validate([
            'items' => 'nullable|array',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.real_amount' => 'required|integer|min:0',
            'adena_real' => 'nullable|integer|min:0',
            'note' => 'nullable|string|max:255',
            'image_proof' => $this->imageProofRule($request->user()),
        ]);

        $current = $request->user();
        if (! $this->canManageWarehouse($current)) {
            abort(403, 'No tienes permiso para hacer un recheck del warehouse.');
        }
        $cpId = $current->cp_id;

        $gains = []; // item_id => positive delta
        $losses = []; // item_id => positive delta (representing units to subtract)
        $diff = []; // for audit log: [{item_id, before, after, delta}]

        foreach (($request->input('items') ?? []) as $row) {
            $itemId = (int) $row['item_id'];
            $real = (int) $row['real_amount'];
            $current_stock = $this->currentStock($cpId, $itemId);
            $delta = $real - $current_stock;
            if ($delta === 0) {
                continue;
            }
            $diff[] = ['item_id' => $itemId, 'before' => $current_stock, 'after' => $real, 'delta' => $delta];
            if ($delta > 0) {
                $gains[$itemId] = ($gains[$itemId] ?? 0) + $delta;
            } else {
                $losses[$itemId] = ($losses[$itemId] ?? 0) + abs($delta);
            }
        }

        // Adena reconciliation — books the signed delta vs the real vault adena
        // (e.g. items were bought without registering the spend, leaving the
        // recorded adena too high). The signed entry is summed into warehouse
        // adena exactly like any other adena loot entry.
        $adenaDelta = 0;
        $adenaItemId = null;
        if ($request->filled('adena_real')) {
            $adenaDelta = (int) $request->input('adena_real') - $this->currentWarehouseAdena($cpId);
            if ($adenaDelta !== 0) {
                $cp = $current->cp;
                $adenaItem = Item::whereRaw('LOWER(name) = ?', ['adena'])
                    ->when($cp?->chronicle, fn ($q) => $q->where('chronicle', $cp->chronicle))
                    ->first()
                    ?? Item::whereRaw('LOWER(name) = ?', ['adena'])->first();
                if (! $adenaItem) {
                    return back()->withErrors(['adena_real' => 'No hay un item "Adena" configurado para tu crónica.']);
                }
                $adenaItemId = (int) $adenaItem->id;
            }
        }

        if (empty($diff) && $adenaDelta === 0) {
            return back()->with('info', 'Sin cambios — los totales coinciden con el stock actual.');
        }

        $imagePath = $request->hasFile('image_proof') ? $this->storeRecheckImage($request->file('image_proof'), $cpId) : null;

        DB::transaction(function () use ($current, $cpId, $gains, $losses, $imagePath, $diff, $request, $adenaDelta, $adenaItemId) {
            $createReport = function (string $eventType, array $bucket) use ($current, $cpId, $imagePath) {
                $report = LootReport::create([
                    'cp_id' => $cpId,
                    'requested_by_id' => $current->id,
                    'event_type' => $eventType,
                    'status' => 'confirmed',
                    'image_proof' => $imagePath,
                ]);
                foreach ($bucket as $itemId => $amount) {
                    LootEntry::create([
                        'loot_report_id' => $report->id,
                        'item_id' => $itemId,
                        'amount' => $amount,
                    ]);
                }

                return $report;
            };

            $gainReport = ! empty($gains) ? $createReport('WAREHOUSE_RECHECK_GAIN', $gains) : null;
            $lossReport = ! empty($losses) ? $createReport('WAREHOUSE_RECHECK_LOSS', $losses) : null;

            $adenaReport = null;
            if ($adenaDelta !== 0 && $adenaItemId) {
                $adenaReport = LootReport::create([
                    'cp_id' => $cpId,
                    'requested_by_id' => $current->id,
                    'event_type' => $adenaDelta > 0 ? 'WAREHOUSE_RECHECK_GAIN' : 'WAREHOUSE_RECHECK_LOSS',
                    'status' => 'confirmed',
                    'image_proof' => $imagePath,
                ]);
                LootEntry::create([
                    'loot_report_id' => $adenaReport->id,
                    'item_id' => $adenaItemId,
                    'amount' => $adenaDelta, // signed: + adds adena, - removes
                ]);
            }

            AuditLog::create([
                'entity_type' => 'LootReport',
                'entity_id' => ($gainReport?->id ?? $lossReport?->id ?? $adenaReport?->id),
                'user_id' => $current->id,
                'action' => 'WAREHOUSE_RECHECK',
                'old_values' => null,
                'new_values' => [
                    'note' => $request->input('note'),
                    'diff' => $diff,
                    'adena_delta' => $adenaDelta,
                    'gain_report_id' => $gainReport?->id,
                    'loss_report_id' => $lossReport?->id,
                    'adena_report_id' => $adenaReport?->id,
                ],
            ]);
        });

        return back()->with('success', 'Recheck registrado. Stock ajustado.');
    }

    /**
     * Warehouse adena currently recorded for a CP — same formula as index():
     * confirmed, non-voided adena loot entries (minus payouts/grants) plus the
     * ADENA_PAYOUT ledger. Used by the recheck adena reconciliation.
     */
    private function currentWarehouseAdena(int $cpId): int
    {
        $adenaIn = LootEntry::query()
            ->join('items', 'items.id', '=', 'loot_entries.item_id')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->whereNull('loot_reports.voided_at')
            ->whereNotIn('loot_reports.event_type', ['ADENA_PAYOUT', 'ADENA_GRANT'])
            ->whereRaw('LOWER(items.name) = ?', ['adena'])
            ->sum('loot_entries.amount');
        $adenaPaidSum = PointsLog::where('cp_id', $cpId)
            ->where('action_type', 'ADENA_PAYOUT')
            ->sum('adena');

        return max(0, (int) $adenaIn + (int) $adenaPaidSum);
    }

    private function storeRecheckImage($file, int $cpId): string
    {
        $ext = $file->extension() ?: ($file->guessExtension() ?: 'jpg');
        $name = 'recheck_'.Str::uuid().'.'.$ext;

        return $file->storeAs("warehouse_recheck/{$cpId}", $name, 'public');
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

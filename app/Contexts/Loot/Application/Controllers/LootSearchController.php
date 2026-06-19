<?php

namespace App\Contexts\Loot\Application\Controllers;

use App\Contexts\Loot\Domain\Models\Item;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LootSearchController extends Controller
{
    /**
     * Rapid search for items to be used in the loot registration modal.
     */
    public function search(Request $request)
    {
        $user = $request->user();
        $search = $request->input('q');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min(50, (int) $request->input('per_page', 12)));

        if (! $search || strlen($search) < 3) {
            return response()->json([
                'items' => [],
                'pagination' => [
                    'page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'has_more' => false,
                ],
            ]);
        }

        $query = Item::where('name', 'like', "%{$search}%");

        // Filter by CP Chronicle if the user belongs to one
        if ($user->cp_id && $user->cp) {
            $query->where('chronicle', $user->cp->chronicle);
        }

        // Rank by usage_count DESC so items actually present in any CP's
        // loot history / wishlist / recipes float to the top, instead of
        // burying real results under junk catalog entries that match the
        // LIKE. Secondary order by name keeps results stable.
        $paginator = $query
            ->orderByDesc('usage_count')
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'grade', 'icon_name', 'image_url', 'category', 'chronicle', 'market_price', 'npc_sell_price', 'usage_count'], 'page', $page);

        return response()->json([
            'items' => $paginator->items(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    /**
     * Full detail for a single item — powers the global item-detail modal
     * (opened by clicking an item icon anywhere). Includes whether the
     * current user may edit prices (admin / CP leader / accountant).
     */
    public function show(Request $request, Item $item)
    {
        $role = $request->user()?->role?->name;

        return response()->json([
            'id' => $item->id,
            'name' => $item->name,
            'grade' => $item->grade,
            'category' => $item->category,
            'chronicle' => $item->chronicle,
            'icon_name' => $item->icon_name,
            'image_url' => $item->image_url,
            'source' => $item->source,
            'base_points' => $item->base_points,
            'external_id' => $item->external_id,
            'description' => $item->description,
            'market_price' => $item->market_price,
            'npc_sell_price' => $item->npc_sell_price,
            'market_price_updated_at' => $item->market_price_updated_at?->toIso8601String(),
            'market_price_updated_by_name' => $item->market_price_updated_by_name,
            'can_edit_prices' => in_array($role, ['admin', 'cp_leader', 'accountant'], true),
        ]);
    }

    public function updateMarketPrice(Request $request, Item $item)
    {
        // Only officers (admin / CP leader / accountant) may set the market
        // price. Regular members are read-only — the inline editor already
        // toasts them, this is the server-side guard against direct API calls.
        $roleName = $request->user()?->role?->name;
        if (! in_array($roleName, ['admin', 'cp_leader', 'accountant'], true)) {
            abort(403, 'No tienes el rol necesario para fijar el precio de mercado.');
        }

        $data = $request->validate([
            'price' => 'nullable|integer|min:0|max:9999999999',
        ]);

        $price = $data['price'] ?? null;

        // Market price (user wiki) can never undercut the NPC sell
        // price (the in-game baseline). If the user sets it lower the
        // NPC would buy the item for more than the "market" — economic
        // nonsense and a fast way to lose adena. Reject with a 422 so
        // the inline editor surfaces the error.
        if ($price !== null && $item->npc_sell_price !== null && $price < (int) $item->npc_sell_price) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'price' => "Market price (".number_format($price, 0, '.', ' ').") cannot be lower than the NPC sell price (".number_format($item->npc_sell_price, 0, '.', ' ').").",
            ]);
        }

        $now = now();
        $user = $request->user();

        $item->forceFill([
            'market_price' => $price,
            'market_price_updated_at' => $price !== null ? $now : null,
            'market_price_updated_by' => $price !== null ? $user->id : null,
        ])->save();

        // A manual price changes every craft cost that depends on this item.
        app(\App\Contexts\Loot\Domain\Services\CraftedPriceService::class)->forget((string) $item->chronicle);

        if ($request->wantsJson()) {
            return response()->json([
                'item_id' => $item->id,
                'market_price' => $price,
                'market_price_updated_at' => $price !== null ? $now->toIso8601String() : null,
                'market_price_updated_by_name' => $price !== null ? $user->name : null,
            ]);
        }

        return back();
    }

    /**
     * Set the base (NPC sell-back) price of an item. Same officer-only gate
     * as the market price; members are read-only. Used by the Items DB detail
     * editor. Keeps the invariant base <= market: the base price is the floor
     * the market price can't undercut, so it can't exceed an existing market
     * price either.
     */
    public function updateNpcPrice(Request $request, Item $item)
    {
        $roleName = $request->user()?->role?->name;
        if (! in_array($roleName, ['admin', 'cp_leader', 'accountant'], true)) {
            abort(403, 'No tienes el rol necesario para fijar el precio base.');
        }

        $data = $request->validate([
            'price' => 'nullable|integer|min:0|max:9999999999',
        ]);

        $price = $data['price'] ?? null;

        if ($price !== null && $item->market_price !== null && $price > (int) $item->market_price) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'price' => 'Base price ('.number_format($price, 0, '.', ' ').') cannot be higher than the market price ('.number_format($item->market_price, 0, '.', ' ').').',
            ]);
        }

        $item->forceFill(['npc_sell_price' => $price])->save();

        // The base price feeds craft-cost fallbacks, so bust the cache.
        app(\App\Contexts\Loot\Domain\Services\CraftedPriceService::class)->forget((string) $item->chronicle);

        if ($request->wantsJson()) {
            return response()->json([
                'item_id' => $item->id,
                'npc_sell_price' => $price,
                'npc_sell_price_updated_at' => null,
                'npc_sell_price_updated_by_name' => null,
            ]);
        }

        return back();
    }
}

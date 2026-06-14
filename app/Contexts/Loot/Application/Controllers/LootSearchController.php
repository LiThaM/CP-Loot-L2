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
            ->paginate($perPage, ['id', 'name', 'grade', 'icon_name', 'image_url', 'category', 'chronicle', 'market_price', 'usage_count'], 'page', $page);

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
}

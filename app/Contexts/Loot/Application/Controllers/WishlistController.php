<?php

namespace App\Contexts\Loot\Application\Controllers;

use App\Contexts\Loot\Domain\Models\Wishlist;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * Store a new item in the wishlist.
     */
    public function store(Request $request)
    {
        $request->validate([
            'item_id' => 'required|exists:items,id',
            'priority' => 'required|in:low,medium,high',
        ]);

        $user = $request->user();

        if (! $user->cp_id || $user->id !== $user->cp->leader_id) {
            abort(403, 'Solo el líder puede gestionar la wishlist.');
        }

        Wishlist::updateOrCreate(
            ['cp_id' => $user->cp_id, 'item_id' => $request->item_id],
            ['priority' => $request->priority]
        );

        return back()->with('success', 'Ítem añadido a la wishlist.');
    }

    /**
     * Remove an item from the wishlist.
     */
    public function destroy(Request $request, Wishlist $wishlist)
    {
        $user = $request->user();

        if ($user->id !== $wishlist->cp->leader_id) {
            abort(403, 'Solo el líder puede gestionar la wishlist.');
        }

        $wishlist->delete();

        return back()->with('success', 'Ítem eliminado de la wishlist.');
    }
}

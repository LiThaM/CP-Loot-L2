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

        if (! $search || strlen($search) < 3) {
            return response()->json([]);
        }

        $query = Item::where('name', 'like', "%{$search}%")
            ->whereRaw('LOWER(name) NOT LIKE ?', ['%not in use%'])
            ->whereRaw('LOWER(name) NOT LIKE ?', ['%not use%']);

        // Filter by CP Chronicle if the user belongs to one
        if ($user->cp_id && $user->cp) {
            $query->where('chronicle', $user->cp->chronicle);
        }

        $items = $query->limit(10)
            ->get(['id', 'name', 'grade', 'icon_name', 'image_url', 'category', 'chronicle']);

        return response()->json($items);
    }
}

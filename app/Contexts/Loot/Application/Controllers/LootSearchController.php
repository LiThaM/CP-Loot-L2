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

        $query = Item::where('name', 'like', "%{$search}%")
            ->whereRaw('LOWER(name) NOT LIKE ?', ['%not in use%'])
            ->whereRaw('LOWER(name) NOT LIKE ?', ['%not use%']);

        // Filter by CP Chronicle if the user belongs to one
        if ($user->cp_id && $user->cp) {
            $query->where('chronicle', $user->cp->chronicle);
        }

        $paginator = $query
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'grade', 'icon_name', 'image_url', 'category', 'chronicle'], 'page', $page);

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
}

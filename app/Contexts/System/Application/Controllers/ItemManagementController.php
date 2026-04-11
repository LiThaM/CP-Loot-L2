<?php

namespace App\Contexts\System\Application\Controllers;

use App\Contexts\Loot\Domain\Models\Item;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ItemManagementController extends Controller
{
    private function applyListExcludes($query)
    {
        return $query
            ->whereRaw('LOWER(name) NOT LIKE ?', ['%not in use%'])
            ->whereRaw('LOWER(name) NOT LIKE ?', ['%not use%']);
    }

    public function index(Request $request)
    {
        if (($request->user()?->role?->name ?? null) !== 'admin') {
            abort(403);
        }

        $query = $this->applyListExcludes(Item::query());

        // Search by name
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        // Filter by chronicle
        if ($request->filled('chronicle')) {
            $query->where('chronicle', $request->chronicle);
        }

        // Filter by grade
        if ($request->filled('grade')) {
            $query->where('grade', $request->grade);
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $items = $query->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('System/Items/Index', [
            'items' => $items,
            'filters' => $request->only(['search', 'chronicle', 'grade', 'category']),
            'chronicles' => ['c1', 'c2', 'c3', 'c4', 'c5', 'IL', 'hb', 'LU4', 'masterwork'],
            'grades' => ['NG', 'D', 'C', 'B', 'A', 'S'],
            'categories' => ['Weapon', 'Armor', 'Jewelry', 'Material', 'Recipe', 'EtcItem'],
            'canEdit' => true,
            'indexRouteName' => 'system.items.index',
            'pageTitle' => 'Base de Datos de Items',
        ]);
    }

    public function itemsDb(Request $request)
    {
        $query = $this->applyListExcludes(Item::query());

        $filters = $request->only(['search', 'chronicle', 'grade', 'category']);
        if (! $request->filled('chronicle') && $request->user()?->cp?->chronicle) {
            $filters['chronicle'] = $request->user()->cp->chronicle;
        }

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }
        if (! empty($filters['chronicle'])) {
            $query->where('chronicle', $filters['chronicle']);
        }
        if (! empty($filters['grade'])) {
            $query->where('grade', $filters['grade']);
        }
        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        $items = $query->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('System/Items/Index', [
            'items' => $items,
            'filters' => $filters,
            'chronicles' => ['c1', 'c2', 'c3', 'c4', 'c5', 'IL', 'hb', 'LU4', 'masterwork'],
            'grades' => ['NG', 'D', 'C', 'B', 'A', 'S'],
            'categories' => ['Weapon', 'Armor', 'Jewelry', 'Material', 'Recipe', 'EtcItem'],
            'canEdit' => false,
            'indexRouteName' => 'itemsdb.index',
            'pageTitle' => 'ITEMS DB',
        ]);
    }

    public function update(Request $request, Item $item)
    {
        if (($request->user()?->role?->name ?? null) !== 'admin') {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'grade' => 'nullable|string|max:10',
            'category' => 'nullable|string|max:50',
            'base_points' => 'required|integer|min:0',
            'description' => 'nullable|string',
        ]);

        $item->update($validated);

        return back()->with('success', 'Item updated successfully');
    }

    public function destroy(Item $item)
    {
        if ((request()->user()?->role?->name ?? null) !== 'admin') {
            abort(403);
        }

        $item->delete();

        return back()->with('success', 'Item deleted successfully');
    }
}

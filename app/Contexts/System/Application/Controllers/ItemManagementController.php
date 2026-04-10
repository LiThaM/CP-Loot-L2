<?php

namespace App\Contexts\System\Application\Controllers;

use App\Contexts\Loot\Domain\Models\Item;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ItemManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = Item::query();

        // Search by name
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
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
            'chronicles' => ['c4', 'c5', 'IL', 'masterwork'],
            'grades' => ['NG', 'D', 'C', 'B', 'A', 'S'],
            'categories' => ['Weapon', 'Armor', 'Jewelry', 'Material', 'Recipe', 'EtcItem']
        ]);
    }

    public function update(Request $request, Item $item)
    {
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
        $item->delete();
        return back()->with('success', 'Item deleted successfully');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $categories = MenuCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $query = MenuItem::with('category')
            ->where('is_available', true)
            ->whereHas('category', fn ($q) => $q->where('is_active', true));

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->input('category')));
        }

        if ($request->filled('q')) {
            $query->where('name', 'like', '%'.$request->input('q').'%');
        }

        if ($request->filled('spice')) {
            $query->where('spice_level', $request->input('spice'));
        }

        if ($request->filled('protein')) {
            $query->where('protein_type', $request->input('protein'));
        }

        if ($request->filled('dietary')) {
            $query->where('dietary_type', $request->input('dietary'));
        }

        if ($request->filled('flavor')) {
            $query->where('flavor_profile', $request->input('flavor'));
        }

        $items = $query->orderBy('name')->paginate(9)->withQueryString();

        $filters = [
            'spiceLevels' => MenuItem::whereNotNull('spice_level')->distinct()->pluck('spice_level')->sort()->values(),
            'proteinTypes' => MenuItem::whereNotNull('protein_type')->distinct()->pluck('protein_type')->sort()->values(),
            'dietaryTypes' => MenuItem::whereNotNull('dietary_type')->distinct()->pluck('dietary_type')->sort()->values(),
            'flavorProfiles' => MenuItem::whereNotNull('flavor_profile')->distinct()->pluck('flavor_profile')->sort()->values(),
        ];

        return view('customer.menu.index', [
            'categories' => $categories,
            'items' => $items,
            'filters' => $filters,
        ]);
    }

    public function show(MenuItem $menuItem)
    {
        abort_unless($menuItem->is_available, 404);

        $related = MenuItem::where('menu_category_id', $menuItem->menu_category_id)
            ->where('id', '!=', $menuItem->id)
            ->where('is_available', true)
            ->limit(3)
            ->get();

        return view('customer.menu.show', [
            'item' => $menuItem->load('category'),
            'related' => $related,
        ]);
    }
}

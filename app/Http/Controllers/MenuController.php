<?php

namespace App\Http\Controllers;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\Ai\AiRecommendationOptions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MenuController extends Controller
{
    public function index(Request $request, AiRecommendationOptions $options)
    {
        $request->validate([
            'spice' => ['nullable', 'string', Rule::in($options->availableValues('spice_level'))],
            'protein' => ['nullable', 'string', Rule::in($options->availableValues('protein_type'))],
            'dietary' => ['nullable', 'string', Rule::in($options->availableValues('dietary_type'))],
            'flavor' => ['nullable', 'string', Rule::in($options->availableValues('flavor_profile'))],
        ]);

        $categories = MenuCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $query = MenuItem::with('category')
            ->where('is_available', true)
            ->whereHas('category', fn ($q) => $q->where('is_active', true));

        $catalogTotal = (clone $query)->count();

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

        $filters = $options->forForm();

        return view('customer.menu.index', [
            'categories' => $categories,
            'items' => $items,
            'filters' => $filters,
            'catalogTotal' => $catalogTotal,
            'hasFilters' => $request->anyFilled(['category', 'q', 'spice', 'protein', 'dietary', 'flavor']),
        ]);
    }

    public function show(MenuItem $menuItem)
    {
        abort_unless($menuItem->is_available && $menuItem->category?->is_active, 404);

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

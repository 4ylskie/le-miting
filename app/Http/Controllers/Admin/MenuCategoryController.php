<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMenuCategoryRequest;
use App\Http\Requests\Admin\UpdateMenuCategoryRequest;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Validation\ValidationException;

class MenuCategoryController extends Controller
{
    public function index()
    {
        $categories = MenuCategory::withCount('menuItems')->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.menu.categories.index', [
            'categories' => $categories,
        ]);
    }

    public function create()
    {
        return view('admin.menu.categories.form', [
            'category' => null,
        ]);
    }

    public function store(StoreMenuCategoryRequest $request)
    {
        MenuCategory::create($request->validated());

        return redirect()->route('admin.menu.categories.index')
            ->with('status', 'Category “'.$request->validated('name').'” added.');
    }

    public function edit(MenuCategory $category)
    {
        return view('admin.menu.categories.form', [
            'category' => $category,
        ]);
    }

    public function update(UpdateMenuCategoryRequest $request, MenuCategory $category)
    {
        $category->update($request->validated());

        return redirect()->route('admin.menu.categories.index')
            ->with('status', 'Category updated.');
    }

    public function destroy(MenuCategory $category)
    {
        $itemsCount = MenuItem::withTrashed()->where('menu_category_id', $category->id)->count();

        if ($itemsCount > 0) {
            throw ValidationException::withMessages([
                'category' => sprintf(
                    'Category “%s” still has %d item%s. Reassign or deactivate those items before deleting the category.',
                    $category->name,
                    $itemsCount,
                    $itemsCount === 1 ? '' : 's',
                ),
            ]);
        }

        $category->delete();

        return redirect()->route('admin.menu.categories.index')
            ->with('status', 'Category “'.$category->name.'” deleted.');
    }
}

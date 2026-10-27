<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMenuItemRequest;
use App\Http\Requests\Admin\UpdateMenuItemRequest;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemAllergenAssessment;
use App\Services\MenuAllergenAssessmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MenuItemController extends Controller
{
    public function index(Request $request)
    {
        $items = MenuItem::with('category')
            ->when($request->filled('category'), fn ($query) => $query->where('menu_category_id', $request->integer('category')))
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.$request->string('q')->trim().'%'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.menu.items.index', [
            'items' => $items,
            'categories' => MenuCategory::orderBy('name')->get(),
            'currentCategory' => $request->query('category'),
            'search' => $request->query('q'),
        ]);
    }

    public function create()
    {
        return view('admin.menu.items.form', [
            'item' => null,
            'categories' => $this->categories(),
        ]);
    }

    public function store(StoreMenuItemRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storeImage($request->file('image'));
        }

        unset($data['image']);

        MenuItem::create($data);

        return redirect()->route('admin.menu.items.index')
            ->with('status', 'Menu item “'.$data['name'].'” added.');
    }

    public function edit(MenuItem $menuItem)
    {
        return view('admin.menu.items.form', [
            'item' => $menuItem,
            'categories' => $this->categories(),
        ]);
    }

    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem)
    {
        $data = $request->validated();
        $previousImagePath = $menuItem->image_path;

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storeImage($request->file('image'));
        }

        unset($data['image']);

        DB::transaction(function () use ($menuItem, $data, $request): void {
            $locked = MenuItem::whereKey($menuItem->id)->lockForUpdate()->firstOrFail();
            $locked->fill($data);
            $requiresReview = $locked->isDirty(MenuItemAllergenAssessment::RELEVANT_FIELDS);
            $locked->save();
            if ($requiresReview) {
                app(MenuAllergenAssessmentService::class)->invalidate($locked, $request->user(), 'Menu description or recipe-related attributes changed.');
            }
            $menuItem->setRawAttributes($locked->getAttributes(), true);
        });

        if (isset($data['image_path'])) {
            $this->deleteMenuImage($previousImagePath);
        }

        return redirect()->route('admin.menu.items.index')
            ->with('status', 'Menu item “'.$menuItem->name.'” updated.');
    }

    public function toggleAvailability(MenuItem $menuItem)
    {
        $menuItem->update(['is_available' => ! $menuItem->is_available]);

        return back()->with('status', sprintf(
            'Menu item “%s” is now %s.',
            $menuItem->name,
            $menuItem->is_available ? 'available' : 'unavailable',
        ));
    }

    public function destroy(MenuItem $menuItem): RedirectResponse
    {
        $menuItem->delete();

        return redirect()->route('admin.menu.items.index')
            ->with('status', 'Menu item “'.$menuItem->name.'” was removed from the customer menu. Historical records were preserved.');
    }

    /**
     * @return Collection<int, MenuCategory>
     */
    private function categories(): Collection
    {
        return MenuCategory::orderBy('sort_order')->orderBy('name')->get();
    }

    private function storeImage(UploadedFile $image): string
    {
        return $image->store('menu', 'public');
    }

    private function deleteMenuImage(?string $path): void
    {
        if ($path !== null && str_starts_with($path, 'menu/')) {
            Storage::disk('public')->delete($path);
        }
    }
}

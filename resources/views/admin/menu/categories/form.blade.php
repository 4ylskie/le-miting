@extends('admin.layouts.app', ['title' => $category ? 'Edit Category' : 'Add Category'])

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.menu.categories.index') }}" class="lm-admin-target text-sm font-semibold text-brand-700 hover:text-brand-800">← Back to categories</a>
    </div>

    @error('category')
        <p class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">{{ $message }}</p>
    @enderror

    <div class="max-w-xl rounded-xl border border-brand-100 bg-white p-6 shadow-sm">
        <form
            method="POST"
            action="{{ $category ? route('admin.menu.categories.update', $category) : route('admin.menu.categories.store') }}"
            class="space-y-5"
        >
            @csrf
            @if ($category)
                @method('PATCH')
            @endif

            <div>
                <label for="name" class="block text-sm font-medium text-text">Name</label>
                <input
                    id="name"
                    type="text"
                    name="name"
                    value="{{ old('name', $category?->name) }}"
                    required
                    maxlength="100"
                    class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
                    @if ($errors->has('name')) aria-invalid="true" aria-describedby="name-error" @endif
                >
                @error('name')
                    <p id="name-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="slug" class="block text-sm font-medium text-text">Slug</label>
                <input
                    id="slug"
                    type="text"
                    name="slug"
                    value="{{ old('slug', $category?->slug) }}"
                    required
                    maxlength="120"
                    placeholder="e.g. main-course"
                    class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
                    @if ($errors->has('slug')) aria-invalid="true" aria-describedby="slug-error" @endif
                >
                @error('slug')
                    <p id="slug-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="sort_order" class="block text-sm font-medium text-text">Sort order</label>
                <input
                    id="sort_order"
                    type="number"
                    name="sort_order"
                    value="{{ old('sort_order', $category?->sort_order ?? 0) }}"
                    min="0"
                    class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
                    @if ($errors->has('sort_order')) aria-invalid="true" aria-describedby="sort-order-error" @endif
                >
                <p class="mt-1 text-xs text-text-muted">Lower numbers appear first in the menu.</p>
                @error('sort_order')
                    <p id="sort-order-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-2">
                <input type="hidden" name="is_active" value="0">
                <input
                    id="is_active"
                    type="checkbox"
                    name="is_active"
                    value="1"
                    class="h-4 w-4 rounded border-brand-300 text-primary focus:ring-primary"
                    @checked(old('is_active', $category?->is_active ?? true))
                    @if ($errors->has('is_active')) aria-invalid="true" aria-describedby="is-active-error" @endif
                >
                <label for="is_active" class="text-sm font-medium text-text">Active (visible on the menu)</label>
                @error('is_active')
                    <p id="is-active-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>

            @if ($category)
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-brand-100 pt-5">
                    <div class="space-y-2 text-sm text-text-muted">
                        <p>Not archived: {{ $category->current_items_count }} · Archived: {{ $category->archived_items_count }} · Total assigned: {{ $category->total_assigned_count }}</p>
                        @if ($category->total_assigned_count > 0)
                            <p>This category cannot be deleted while menu items still reference it, including archived items. Making an item unavailable or removing it from the public menu does not clear that reference.</p>
                            @if ($category->current_items_count > 0)
                                <p>Reassign non-archived items to another category to clear their references.</p>
                            @endif
                        @else
                            <p>This category has no assigned items and can be deleted.</p>
                        @endif
                    </div>
                    @if ($category->total_assigned_count === 0)
                        <button type="submit" form="category-delete-form" class="text-sm font-semibold text-red-700 hover:text-red-800">Delete category</button>
                    @elseif ($category->current_items_count > 0)
                        <a href="{{ route('admin.menu.items.index', ['category' => $category->id]) }}" class="lm-admin-target text-sm font-semibold text-brand-700 hover:text-brand-800">View items</a>
                    @endif
                </div>
            @endif

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-primary px-6 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">
                    {{ $category ? 'Save changes' : 'Add category' }}
                </button>
                <a href="{{ route('admin.menu.categories.index') }}" class="lm-admin-target text-sm font-semibold text-brand-700 hover:text-brand-800">Cancel</a>
            </div>
        </form>

        @if ($category && $category->total_assigned_count === 0)
            <form id="category-delete-form" method="POST" action="{{ route('admin.menu.categories.destroy', $category) }}" data-confirm-submit="Delete this category?">
                @csrf
                @method('DELETE')
            </form>
        @endif
    </div>
@endsection

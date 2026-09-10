@extends('admin.layouts.app', ['title' => $category ? 'Edit Category' : 'Add Category'])

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.menu.categories.index') }}" class="text-sm font-semibold text-brand-700 hover:text-brand-800">← Back to categories</a>
    </div>

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
                >
                @error('name')
                    <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
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
                >
                @error('slug')
                    <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
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
                >
                <p class="mt-1 text-xs text-text-muted">Lower numbers appear first in the menu.</p>
                @error('sort_order')
                    <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-2">
                <input
                    id="is_active"
                    type="checkbox"
                    name="is_active"
                    value="1"
                    class="h-4 w-4 rounded border-brand-300 text-primary focus:ring-primary"
                    @checked(old('is_active', $category?->is_active ?? true))
                >
                <label for="is_active" class="text-sm font-medium text-text">Active (visible on the menu)</label>
                @error('is_active')
                    <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>

            @if ($category && $category->menuItems()->count() > 0)
                <div class="flex items-center justify-between gap-3 border-t border-brand-100 pt-5">
                    <p class="text-sm text-text-muted">
                        This category has {{ $category->menuItems()->count() }} item{{ $category->menuItems()->count() === 1 ? '' : 's' }}.
                    </p>
                    <a href="{{ route('admin.menu.items.index') }}" class="text-sm font-semibold text-brand-700 hover:text-brand-800">View items</a>
                </div>
            @elseif ($category)
                <div class="flex items-center justify-between gap-3 border-t border-brand-100 pt-5">
                    <p class="text-sm text-text-muted">This category has no items and can be deleted.</p>
                    <form method="POST" action="{{ route('admin.menu.categories.destroy', $category) }}" onsubmit="return confirm('Delete this category?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm font-semibold text-red-700 hover:text-red-800">Delete category</button>
                    </form>
                </div>
            @endif

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-primary px-6 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">
                    {{ $category ? 'Save changes' : 'Add category' }}
                </button>
                <a href="{{ route('admin.menu.categories.index') }}" class="text-sm font-semibold text-brand-700 hover:text-brand-800">Cancel</a>
            </div>
        </form>
    </div>
@endsection
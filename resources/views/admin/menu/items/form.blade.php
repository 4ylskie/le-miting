@extends('admin.layouts.app', ['title' => $item ? 'Edit '.$item->name : 'Add Menu Item'])

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.menu.items.index') }}" class="text-sm font-semibold text-brand-700 hover:text-brand-800">← Back to menu items</a>
    </div>

    <div class="max-w-2xl rounded-xl border border-brand-100 bg-white p-6 shadow-sm">
        <form
            method="POST"
            action="{{ $item ? route('admin.menu.items.update', $item) : route('admin.menu.items.store') }}"
            class="space-y-5"
            enctype="multipart/form-data"
        >
            @csrf
            @if ($item)
                @method('PATCH')
            @endif

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="name" class="block text-sm font-medium text-text">Name</label>
                    <input
                        id="name"
                        type="text"
                        name="name"
                        value="{{ old('name', $item?->name) }}"
                        required
                        maxlength="150"
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
                        value="{{ old('slug', $item?->slug) }}"
                        required
                        maxlength="160"
                        placeholder="e.g. nasi-lemak"
                        class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
                    >
                    @error('slug')
                        <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="menu_category_id" class="block text-sm font-medium text-text">Category</label>
                    <select id="menu_category_id" name="menu_category_id" class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary" required>
                        <option value="">Select a category…</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('menu_category_id', $item?->menu_category_id) === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('menu_category_id')
                        <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="price" class="block text-sm font-medium text-text">Price (RM)</label>
                    <input
                        id="price"
                        type="number"
                        name="price"
                        value="{{ old('price', $item?->price) }}"
                        required
                        min="0"
                        max="99999"
                        step="0.01"
                        class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
                    >
                    @error('price')
                        <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-text">Description</label>
                <textarea
                    id="description"
                    name="description"
                    rows="3"
                    maxlength="2000"
                    required
                    class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
                >{{ old('description', $item?->description) }}</textarea>
                @error('description')
                    <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="image" class="block text-sm font-medium text-text">Image</label>
                @if ($item?->image_path)
                    <p class="mt-1 text-xs text-text-muted">Current image: storage/{{ $item->image_path }}</p>
                @endif
                <input
                    id="image"
                    type="file"
                    name="image"
                    accept="image/jpeg,image/png,image/webp"
                    class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text"
                >
                @error('image')
                    <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="spice_level" class="block text-sm font-medium text-text">Spice level</label>
                    <input id="spice_level" type="text" name="spice_level" value="{{ old('spice_level', $item?->spice_level) }}" maxlength="50" placeholder="e.g. spicy" class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text">
                    @error('spice_level')
                        <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="protein_type" class="block text-sm font-medium text-text">Protein type</label>
                    <input id="protein_type" type="text" name="protein_type" value="{{ old('protein_type', $item?->protein_type) }}" maxlength="50" placeholder="e.g. chicken" class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text">
                    @error('protein_type')
                        <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="dietary_type" class="block text-sm font-medium text-text">Dietary type</label>
                    <input id="dietary_type" type="text" name="dietary_type" value="{{ old('dietary_type', $item?->dietary_type) }}" maxlength="50" placeholder="e.g. vegetarian" class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text">
                    @error('dietary_type')
                        <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="flavor_profile" class="block text-sm font-medium text-text">Flavour profile</label>
                    <input id="flavor_profile" type="text" name="flavor_profile" value="{{ old('flavor_profile', $item?->flavor_profile) }}" maxlength="50" placeholder="e.g. umami" class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text">
                    @error('flavor_profile')
                        <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="sort_order" class="block text-sm font-medium text-text">Sort order</label>
                    <input id="sort_order" type="number" name="sort_order" value="{{ old('sort_order', $item?->sort_order ?? 0) }}" min="0" class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text">
                    @error('sort_order')
                        <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex flex-wrap gap-6">
                <div class="flex items-center gap-2">
                    <input id="is_available" type="checkbox" name="is_available" value="1" class="h-4 w-4 rounded border-brand-300 text-primary focus:ring-primary" @checked(old('is_available', $item?->is_available ?? true))>
                    <label for="is_available" class="text-sm font-medium text-text">Available for ordering</label>
                </div>

                <div class="flex items-center gap-2">
                    <input id="is_featured" type="checkbox" name="is_featured" value="1" class="h-4 w-4 rounded border-brand-300 text-primary focus:ring-primary" @checked(old('is_featured', $item?->is_featured ?? false))>
                    <label for="is_featured" class="text-sm font-medium text-text">Featured on home page</label>
                </div>
            </div>
            @error('is_available')
                <p class="text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
            @error('is_featured')
                <p class="text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-primary px-6 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">
                    {{ $item ? 'Save changes' : 'Add item' }}
                </button>
                <a href="{{ route('admin.menu.items.index') }}" class="text-sm font-semibold text-brand-700 hover:text-brand-800">Cancel</a>
            </div>
        </form>
    </div>
@endsection
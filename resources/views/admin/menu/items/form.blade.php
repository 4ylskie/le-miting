@extends('admin.layouts.app', ['title' => $item ? 'Edit '.$item->name : 'Add Menu Item'])

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.menu.items.index') }}" class="lm-admin-target text-sm font-semibold text-brand-700 hover:text-brand-800">← Back to menu items</a>
    </div>

    <div class="max-w-2xl rounded-xl border border-brand-100 bg-white p-6 shadow-sm">
        @if ($item)
            <a href="{{ route('admin.menu.allergens.index', $item) }}" class="lm-admin-target mb-4 font-semibold text-brand-700">Manage allergen assessments</a>
            <p class="mb-6 text-sm text-text-muted">Recipe-related menu edits invalidate assessments. Record ingredient, supplier or preparation changes on the assessment page before relying on prior reviews.</p>
        @endif
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
                        value="{{ old('slug', $item?->slug) }}"
                        required
                        maxlength="160"
                        placeholder="e.g. nasi-lemak"
                        class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
                        @if ($errors->has('slug')) aria-invalid="true" aria-describedby="slug-error" @endif
                    >
                    @error('slug')
                        <p id="slug-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="menu_category_id" class="block text-sm font-medium text-text">Category</label>
                    <select id="menu_category_id" name="menu_category_id" class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary" required @if ($errors->has('menu_category_id')) aria-invalid="true" aria-describedby="menu-category-error" @endif>
                        <option value="">Select a category…</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('menu_category_id', $item?->menu_category_id) === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('menu_category_id')
                        <p id="menu-category-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
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
                        @if ($errors->has('price')) aria-invalid="true" aria-describedby="price-error" @endif
                    >
                    @error('price')
                        <p id="price-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
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
                    @if ($errors->has('description')) aria-invalid="true" aria-describedby="description-error" @endif
                >{{ old('description', $item?->description) }}</textarea>
                @error('description')
                    <p id="description-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
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
                    @if ($errors->has('image')) aria-invalid="true" aria-describedby="image-error" @endif
                >
                @error('image')
                    <p id="image-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="spice_level" class="block text-sm font-medium text-text">Spice level</label>
                    <select id="spice_level" name="spice_level" class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text" @if ($errors->has('spice_level')) aria-invalid="true" aria-describedby="spice-level-error" @endif>
                        @foreach (\App\Services\Ai\AiRecommendationOptions::VALUES['spice_level'] as $option)
                            <option value="{{ $option }}" @selected(old('spice_level', $item?->spice_level ?? 'none') === $option)>{{ \App\Services\Ai\AiRecommendationOptions::label('spice_level', $option) }}</option>
                        @endforeach
                    </select>
                    @error('spice_level')
                        <p id="spice-level-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="protein_type" class="block text-sm font-medium text-text">Protein type</label>
                    <select id="protein_type" name="protein_type" class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text" @if ($errors->has('protein_type')) aria-invalid="true" aria-describedby="protein-type-error" @endif>
                        @foreach (\App\Services\Ai\AiRecommendationOptions::VALUES['protein_type'] as $option)
                            <option value="{{ $option }}" @selected(old('protein_type', $item?->protein_type ?? 'none') === $option)>{{ \App\Services\Ai\AiRecommendationOptions::label('protein_type', $option) }}</option>
                        @endforeach
                    </select>
                    @error('protein_type')
                        <p id="protein-type-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="dietary_type" class="block text-sm font-medium text-text">Dietary type</label>
                    <select id="dietary_type" name="dietary_type" class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text" @if ($errors->has('dietary_type')) aria-invalid="true" aria-describedby="dietary-type-error" @endif>
                        <option value="">Not specified</option>
                        @foreach (\App\Services\Ai\AiRecommendationOptions::VALUES['dietary_type'] as $option)
                            <option value="{{ $option }}" @selected(old('dietary_type', $item?->dietary_type) === $option)>{{ \App\Services\Ai\AiRecommendationOptions::label('dietary_type', $option) }}</option>
                        @endforeach
                    </select>
                    @error('dietary_type')
                        <p id="dietary-type-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="flavor_profile" class="block text-sm font-medium text-text">Flavour profile</label>
                    <select id="flavor_profile" name="flavor_profile" class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text" @if ($errors->has('flavor_profile')) aria-invalid="true" aria-describedby="flavor-profile-error" @endif>
                        <option value="">Not specified</option>
                        @foreach (\App\Services\Ai\AiRecommendationOptions::VALUES['flavor_profile'] as $option)
                            <option value="{{ $option }}" @selected(old('flavor_profile', $item?->flavor_profile) === $option)>{{ \App\Services\Ai\AiRecommendationOptions::label('flavor_profile', $option) }}</option>
                        @endforeach
                    </select>
                    @error('flavor_profile')
                        <p id="flavor-profile-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="sort_order" class="block text-sm font-medium text-text">Sort order</label>
                    <input id="sort_order" type="number" name="sort_order" value="{{ old('sort_order', $item?->sort_order ?? 0) }}" min="0" class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text" @if ($errors->has('sort_order')) aria-invalid="true" aria-describedby="sort-order-error" @endif>
                    @error('sort_order')
                        <p id="sort-order-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex flex-wrap gap-6">
                <div class="flex items-center gap-2">
                    <input id="is_available" type="checkbox" name="is_available" value="1" class="h-4 w-4 rounded border-brand-300 text-primary focus:ring-primary" @checked(old('is_available', $item?->is_available ?? true)) @if ($errors->has('is_available')) aria-invalid="true" aria-describedby="is-available-error" @endif>
                    <label for="is_available" class="text-sm font-medium text-text">Available for ordering</label>
                </div>

                <div class="flex items-center gap-2">
                    <input id="is_featured" type="checkbox" name="is_featured" value="1" class="h-4 w-4 rounded border-brand-300 text-primary focus:ring-primary" @checked(old('is_featured', $item?->is_featured ?? false)) @if ($errors->has('is_featured')) aria-invalid="true" aria-describedby="is-featured-error" @endif>
                    <label for="is_featured" class="text-sm font-medium text-text">Featured on home page</label>
                </div>
            </div>
            @error('is_available')
                <p id="is-available-error" class="text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
            @error('is_featured')
                <p id="is-featured-error" class="text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-primary px-6 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">
                    {{ $item ? 'Save changes' : 'Add item' }}
                </button>
                <a href="{{ route('admin.menu.items.index') }}" class="lm-admin-target text-sm font-semibold text-brand-700 hover:text-brand-800">Cancel</a>
            </div>
        </form>
    </div>
@endsection

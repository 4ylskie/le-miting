@extends('admin.layouts.app', ['title' => 'Menu Items'])

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('admin.menu.items.index') }}" class="flex flex-wrap items-center gap-2">
            <label for="q" class="sr-only">Search menu items</label>
            <input
                id="q"
                type="search"
                name="q"
                value="{{ $search }}"
                placeholder="Search items…"
                class="rounded-md border border-brand-200 bg-white px-3 py-2 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
            >
            <label for="category" class="sr-only">Filter by category</label>
            <select id="category" name="category" onchange="this.form.submit()" class="rounded-md border border-brand-200 bg-white px-3 py-2 text-sm text-text">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) $currentCategory === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Search</button>
            @if ($search || $currentCategory)
                <a href="{{ route('admin.menu.items.index') }}" class="text-sm font-semibold text-brand-700 hover:text-brand-800">Clear</a>
            @endif
        </form>

        <a href="{{ route('admin.menu.items.create') }}" class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">
            Add item
        </a>
    </div>

    @if (session('status'))
        <p class="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</p>
    @endif

    @if ($items->isEmpty())
        <p class="rounded-md bg-brand-50 px-4 py-4 text-sm text-brand-800">
            No menu items found.
        </p>
    @else
        <div class="overflow-x-auto rounded-xl border border-brand-100 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-brand-100 text-sm">
                <thead class="bg-brand-50">
                    <tr>
                        <th class="px-5 py-3 text-left font-semibold text-text">Item</th>
                        <th class="px-5 py-3 text-left font-semibold text-text">Category</th>
                        <th class="px-5 py-3 text-left font-semibold text-text">Price</th>
                        <th class="px-5 py-3 text-left font-semibold text-text">Availability</th>
                        <th class="px-5 py-3 text-left font-semibold text-text">Featured</th>
                        <th class="px-5 py-3 text-left font-semibold text-text"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-100">
                    @foreach ($items as $item)
                        <tr class="hover:bg-brand-50/50">
                            <td class="whitespace-nowrap px-5 py-3 font-medium text-text">{{ $item->name }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-text">{{ $item->category?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-text">RM {{ number_format((float) $item->price, 2) }}</td>
                            <td class="whitespace-nowrap px-5 py-3">
                                <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $item->is_available ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-700' }}">{{ $item->is_available ? 'Available' : 'Unavailable' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3">
                                @if ($item->is_featured)
                                    <span class="inline-block rounded-full bg-brand-100 px-2.5 py-0.5 text-xs font-medium text-brand-800">Featured</span>
                                @else
                                    <span class="text-text-muted">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <form method="POST" action="{{ route('admin.menu.items.toggle-availability', $item) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-sm font-semibold {{ $item->is_available ? 'text-red-700 hover:text-red-800' : 'text-green-700 hover:text-green-800' }}">
                                            {{ $item->is_available ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                    <a href="{{ route('admin.menu.items.edit', $item) }}" class="font-semibold text-brand-700 hover:text-brand-800">Edit →</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $items->links() }}
        </div>
    @endif
@endsection
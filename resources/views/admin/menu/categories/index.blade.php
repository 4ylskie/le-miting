@extends('admin.layouts.app', ['title' => 'Categories'])

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-text-muted">Menu sections shown to guests while browsing.</p>
        <a href="{{ route('admin.menu.categories.create') }}" class="lm-admin-target rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">
            Add category
        </a>
    </div>

    @if (session('status'))
        <p class="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</p>
    @endif

    @error('category')
        <p class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 font-medium" role="alert">{{ $message }}</p>
    @enderror

    @if ($categories->isEmpty())
        <p class="rounded-md bg-brand-50 px-4 py-4 text-sm text-brand-800">
            No categories yet. Add categories before adding menu items.
        </p>
    @else
        <div class="overflow-x-auto rounded-xl border border-brand-100 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-brand-100 text-sm">
                <thead class="bg-brand-50">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-left font-semibold text-text">Name</th>
                        <th scope="col" class="px-5 py-3 text-left font-semibold text-text">Slug</th>
                        <th scope="col" class="px-5 py-3 text-left font-semibold text-text">Not archived</th>
                        <th scope="col" class="px-5 py-3 text-left font-semibold text-text">Archived</th>
                        <th scope="col" class="px-5 py-3 text-left font-semibold text-text">Total assigned</th>
                        <th scope="col" class="px-5 py-3 text-left font-semibold text-text">Status</th>
                        <th scope="col" class="px-5 py-3 text-left font-semibold text-text"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-100">
                    @foreach ($categories as $category)
                        <tr class="hover:bg-brand-50/50">
                            <td class="whitespace-nowrap px-5 py-3 font-medium text-text">{{ $category->name }}</td>
                            <td class="whitespace-nowrap px-5 py-3 font-mono text-text-muted">{{ $category->slug }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-text">{{ $category->current_items_count }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-text">{{ $category->archived_items_count }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-text">{{ $category->total_assigned_count }}</td>
                            <td class="whitespace-nowrap px-5 py-3">
                                <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $category->is_active ? 'bg-green-50 text-green-800' : 'bg-brand-100 text-brand-800' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <a href="{{ route('admin.menu.categories.edit', $category) }}" class="font-semibold text-brand-700 hover:text-brand-800">Edit <span class="sr-only">category {{ $category->name }}</span> →</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

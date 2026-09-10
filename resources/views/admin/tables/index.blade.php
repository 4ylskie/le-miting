@extends('admin.layouts.app', ['title' => 'Tables'])

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-text-muted">Manage tables and their maintenance state.</p>
        <a href="{{ route('admin.tables.create') }}" class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">
            Add table
        </a>
    </div>

    @if (session('status'))
        <p class="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</p>
    @endif

    @if ($tables->isEmpty())
        <p class="rounded-md bg-brand-50 px-4 py-4 text-sm text-brand-800">
            No tables yet. Add the restaurant's tables to start taking reservations.
        </p>
    @else
        <div class="overflow-x-auto rounded-xl border border-brand-100 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-brand-100 text-sm">
                <thead class="bg-brand-50">
                    <tr>
                        <th class="px-5 py-3 text-left font-semibold text-text">Table</th>
                        <th class="px-5 py-3 text-left font-semibold text-text">Capacity</th>
                        <th class="px-5 py-3 text-left font-semibold text-text">Status</th>
                        <th class="px-5 py-3 text-left font-semibold text-text">Upcoming reservations</th>
                        <th class="px-5 py-3 text-left font-semibold text-text"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-100">
                    @foreach ($tables as $table)
                        <tr class="hover:bg-brand-50/50">
                            <td class="whitespace-nowrap px-5 py-3 font-medium text-text">Table {{ $table->table_number }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-text">{{ $table->capacity }}</td>
                            <td class="whitespace-nowrap px-5 py-3">
                                <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $table->status === 'maintenance' ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-800' }}">{{ ucfirst($table->status) }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-text">{{ $table->upcoming_count }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <a href="{{ route('admin.tables.edit', $table) }}" class="font-semibold text-brand-700 hover:text-brand-800">Edit →</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
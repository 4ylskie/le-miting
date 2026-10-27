@extends('admin.layouts.app', ['title' => $table ? 'Edit Table '.$table->table_number : 'Add Table'])

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.tables.index') }}" class="lm-admin-target text-sm font-semibold text-brand-700 hover:text-brand-800">← Back to tables</a>
    </div>

    <div class="max-w-xl rounded-xl border border-brand-100 bg-white p-6 shadow-sm">
        <form
            method="POST"
            action="{{ $table ? route('admin.tables.update', $table) : route('admin.tables.store') }}"
            class="space-y-5"
        >
            @csrf
            @if ($table)
                @method('PATCH')
                <input type="hidden" name="expected_capacity" value="{{ $table->capacity }}">
                <input type="hidden" name="expected_status" value="{{ $table->status }}">
                @error('expected_capacity')
                    <p class="text-sm text-red-600" role="alert">{{ $message }} Refresh the page before trying again.</p>
                @enderror
                @error('expected_status')
                    <p class="text-sm text-red-600" role="alert">{{ $message }} Refresh the page before trying again.</p>
                @enderror
            @endif

            <div>
                <label for="table_number" class="block text-sm font-medium text-text">Table number</label>
                <input
                    id="table_number"
                    type="text"
                    name="table_number"
                    value="{{ old('table_number', $table?->table_number) }}"
                    required
                    maxlength="20"
                    class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
                    @if ($errors->has('table_number')) aria-invalid="true" aria-describedby="table-number-error" @endif
                >
                @error('table_number')
                    <p id="table-number-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="capacity" class="block text-sm font-medium text-text">Seating capacity</label>
                <select
                    id="capacity"
                    name="capacity"
                    required
                    class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
                    @if ($errors->has('capacity')) aria-invalid="true" aria-describedby="capacity-error" @endif
                >
                    <option value="">Choose capacity</option>
                    @foreach (config('reservation.capacity_rules') as $capacity => $minimum)
                        <option value="{{ $capacity }}" @selected((string) old('capacity', $table?->capacity) === (string) $capacity)>{{ $capacity }} seats (parties {{ $minimum }}–{{ $capacity }})</option>
                    @endforeach
                </select>
                @error('capacity')
                    <p id="capacity-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="status" class="block text-sm font-medium text-text">Status</label>
                <select id="status" name="status" class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary" @if ($errors->has('status')) aria-invalid="true" aria-describedby="status-error" @endif>
                    <option value="available" @selected(old('status', $table?->status) === 'available')>Available</option>
                    <option value="maintenance" @selected(old('status', $table?->status) === 'maintenance')>Maintenance</option>
                </select>
                @error('status')
                    <p id="status-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-primary px-6 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">
                    {{ $table ? 'Save changes' : 'Add table' }}
                </button>
                <a href="{{ route('admin.tables.index') }}" class="lm-admin-target text-sm font-semibold text-brand-700 hover:text-brand-800">Cancel</a>
            </div>
        </form>
    </div>
@endsection

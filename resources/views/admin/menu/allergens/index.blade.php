@extends('admin.layouts.app', ['title' => 'Allergen assessments — '.$item->name])

@section('content')
    <a class="lm-admin-target mb-4 font-semibold text-brand-700" href="{{ route('admin.menu.items.edit', $item) }}">Back to {{ $item->name }}</a>
    <p class="mb-4">Admin records only. Missing or invalidated assessments mean unknown. Recipe absence does not establish cross-contact absence or allergy safety. Customer allergy recommendations remain blocked.</p>
    <div class="overflow-x-auto rounded-xl border border-brand-100 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead><tr class="border-b border-brand-100">
                <th scope="col" class="p-4">Allergen</th><th scope="col" class="p-4">Recipe</th>
                <th scope="col" class="p-4">Cross-contact</th><th scope="col" class="p-4">Review provenance</th><th scope="col" class="p-4">Action</th>
            </tr></thead>
            <tbody>
            @foreach ($allergens as $allergen)
                @php
                    $assessment = $assessments->get($allergen->id);
                    $current = $assessment?->isCurrentFor($item) ?? false;
                @endphp
                <tr class="border-b border-brand-100">
                    <th scope="row" class="p-4">{{ $allergen->name }}</th>
                    <td class="p-4">{{ $current ? \App\Models\MenuItemAllergenAssessment::RECIPE[$assessment->recipe_status] : 'Unknown' }}</td>
                    <td class="p-4">{{ $current ? \App\Models\MenuItemAllergenAssessment::CROSS_CONTACT[$assessment->cross_contact_status] : 'Unknown' }}</td>
                    <td class="p-4">
                        @if ($assessment)
                            {{ $current ? 'Recorded' : 'Invalidated or stale — review required' }}<br>
                            {{ $assessment->reviewer?->name }} · {{ $assessment->reviewed_at->format('Y-m-d H:i:s') }} UTC
                        @else
                            Not reviewed
                        @endif
                    </td>
                    <td class="p-4"><a class="lm-admin-target font-semibold text-brand-700" href="{{ route('admin.menu.allergens.edit', [$item, $allergen]) }}" aria-label="Review {{ $allergen->name }} assessment for {{ $item->name }}">Review</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('admin.menu.allergens.invalidate', $item) }}" class="mt-6 max-w-2xl space-y-4 rounded-xl border border-brand-100 bg-white p-6 shadow-sm">
        @csrf
        @method('PATCH')
        <h2 class="text-lg font-semibold">Invalidate assessments after a change</h2>
        <p class="text-sm">Record recipe, ingredient, supplier or preparation changes here. This conservatively invalidates all existing assessments for this item and retains their last review evidence. No automatic expiry applies.</p>
        <div>
            <label for="change_type" class="block font-medium">Change type</label>
            <select id="change_type" name="change_type" required class="mt-1 min-h-11 w-full rounded-md border border-brand-200 px-3" @if ($errors->has('change_type')) aria-invalid="true" aria-describedby="change_type-error" @endif>
                <option value="">Choose a change type</option>
                @foreach (['recipe', 'ingredients', 'supplier', 'preparation'] as $type)
                    <option value="{{ $type }}" @selected(old('change_type') === $type)>{{ ucfirst($type) }}</option>
                @endforeach
            </select>
            @error('change_type')<p id="change_type-error" role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="reason" class="block font-medium">Change details / reference</label>
            <textarea id="reason" name="reason" required maxlength="1000" rows="3" class="mt-1 w-full rounded-md border border-brand-200 p-3" @if ($errors->has('reason')) aria-invalid="true" aria-describedby="reason-error" @endif>{{ old('reason') }}</textarea>
            @error('reason')<p id="reason-error" role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="lm-admin-target rounded-md bg-primary px-4 py-2 font-semibold text-white">Invalidate existing assessments</button>
    </form>
@endsection

@extends('admin.layouts.app', ['title' => $allergen->name.' assessment — '.$item->name])

@section('content')
    @php($current = $assessment?->isCurrentFor($item) ?? false)
    <a class="lm-admin-target mb-4 font-semibold text-brand-700" href="{{ route('admin.menu.allergens.index', $item) }}">Back to allergen assessments</a>
    <div class="max-w-2xl space-y-5 rounded-xl border border-brand-100 bg-white p-6 shadow-sm">
        <p>Use documented restaurant information only. Do not infer ingredients from names, descriptions, images or menu tags. Assess recipe and cross-contact separately. Unknown is not absence; no result establishes allergy safety.</p>
        @if ($assessment)
            <section class="space-y-2 border-b border-brand-100 pb-4" aria-label="Last recorded review">
                <h2 class="font-semibold">Last recorded review{{ $current ? '' : ' — invalidated or stale' }}</h2>
                <p>{{ $assessment->reviewer?->name }} · {{ $assessment->reviewed_at->format('Y-m-d H:i:s') }} UTC</p>
                <p class="whitespace-pre-wrap break-words">{{ $assessment->evidence }}</p>
                @if ($assessment->invalidated_at)
                    <p>Invalidated by {{ $assessment->invalidator?->name }} at {{ $assessment->invalidated_at->format('Y-m-d H:i:s') }} UTC: {{ $assessment->invalidation_reason }}</p>
                @endif
            </section>
        @endif
        <form method="POST" action="{{ route('admin.menu.allergens.update', [$item, $allergen]) }}" class="space-y-5">
            @csrf
            @method('PATCH')
            <input type="hidden" name="context" value="{{ old('context', $context) }}">
            @error('context')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
            @foreach (['recipe_status' => ['Recipe assessment', \App\Models\MenuItemAllergenAssessment::RECIPE], 'cross_contact_status' => ['Cross-contact assessment', \App\Models\MenuItemAllergenAssessment::CROSS_CONTACT]] as $field => [$label, $options])
                <div>
                    <label for="{{ $field }}" class="block font-medium">{{ $label }}</label>
                    <select id="{{ $field }}" name="{{ $field }}" required class="mt-1 min-h-11 w-full rounded-md border border-brand-200 px-3" @if ($errors->has($field)) aria-invalid="true" aria-describedby="{{ $field }}-error" @endif>
                        @foreach ($options as $value => $text)
                            <option value="{{ $value }}" @selected(old($field, $current ? $assessment->{$field} : 'unknown') === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                    @error($field)<p id="{{ $field }}-error" role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <div>
                <label for="evidence" class="block font-medium">Evidence / restaurant reference</label>
                <p id="evidence-help" class="text-sm">Identify the recipe, supplier or preparation information reviewed and what remains unknown. Do not enter customer information.</p>
                <textarea id="evidence" name="evidence" required maxlength="2000" rows="5" class="mt-1 w-full rounded-md border border-brand-200 p-3" aria-describedby="evidence-help{{ $errors->has('evidence') ? ' evidence-error' : '' }}" @if ($errors->has('evidence')) aria-invalid="true" @endif>{{ old('evidence', $current ? $assessment->evidence : '') }}</textarea>
                @error('evidence')<p id="evidence-error" role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="documented_information" class="flex min-h-11 cursor-pointer items-center gap-3">
                    <input id="documented_information" name="documented_information" type="checkbox" value="1" required @checked(old('documented_information')) @if ($errors->has('documented_information')) aria-invalid="true" aria-describedby="documented_information-error" @endif>
                    I used documented restaurant information, not assumptions. Any unassessed aspect is marked unknown.
                </label>
                @error('documented_information')<p id="documented_information-error" role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <p class="text-sm">Saving records your administrator identity and the current UTC review time. A new review replaces the previous assessment; this is not a full revision-history system.</p>
            <button type="submit" class="lm-admin-target rounded-md bg-primary px-4 py-2 font-semibold text-white">Save assessment</button>
        </form>
    </div>
@endsection

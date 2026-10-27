@extends('layouts.master')

@section('title', 'Your Recommendations — Le Miting')

@section('content')
    <header class="lm-container lm-section">
        <p class="lm-eyebrow mb-(--lm-space-md)">Rasa AI</p>
        <h1 class="lm-h1">Your Recommendations</h1>
        <p class="lm-body lm-muted mt-(--lm-space-md) max-w-xl">
            Based on your request, Rasa AI suggests these dishes.
        </p>
    </header>

    <section class="lm-container pb-lm-section" aria-label="Recommended dishes">
        @if ($excludedAllergens->isNotEmpty() && $recommendations->isNotEmpty())
            <aside class="lm-alert lm-allergen-caution mb-(--lm-space-lg)" data-state="warning" role="note" aria-label="Allergen exclusion limitation">
                <p class="font-semibold">Please tell restaurant staff before ordering.</p>
                <p class="mt-(--lm-space-sm)">For {{ $excludedAllergens->pluck('name')->join(', ') }}, current administrator assessments govern when present. If an item has no assessment row, its name and description are checked for matching terms. Menu text may be incomplete and does not confirm ingredients or shared-kitchen handling. Please tell restaurant staff about your needs before ordering.</p>
            </aside>
        @endif
        @if ($notice ?? null)
            <p class="lm-alert mb-(--lm-space-lg)" data-state="{{ $recommendations->isEmpty() ? 'error' : 'info' }}" role="{{ $recommendations->isEmpty() ? 'alert' : 'status' }}">{{ $notice }}</p>
        @endif
        @forelse ($recommendations as $item)
            <x-menu-item :item="$item">
                @php
                    $reasons = [];
                    foreach ([
                        'spice_level' => 'matches your spice preference',
                        'protein_type' => 'matches your protein preference',
                        'dietary_type' => 'is labelled with your dietary preference on the menu',
                        'flavor_profile' => 'suits your flavour preference',
                    ] as $field => $reason) {
                        if (! empty($preferences[$field]) && $item->$field === $preferences[$field]) {
                            $reasons[] = $reason;
                        }
                    }
                    if ($maxPrice ?? null) {
                        $reasons[] = 'is within your RM '.number_format((float) $maxPrice, 2).' per-dish budget';
                    }
                    if (! empty($preferences['spice_levels']) && in_array($item->spice_level, $preferences['spice_levels'], true)) {
                        $reasons[] = 'matches your requested spice range';
                    }
                    if (! empty($preferences['excluded_protein_types'])) {
                        $reasons[] = 'has a different listed primary protein';
                    }
                @endphp
                @if ($reasons)
                    <p class="lm-meta lm-muted">Recommended because it {{ implode(', ', $reasons) }}.</p>
                @endif
                @if ($excludedAllergens->isNotEmpty())
                    <ul class="lm-meta lm-allergen-evidence-list mt-(--lm-space-sm)" aria-label="Allergen exclusion checks">
                        @foreach ($excludedAllergens as $allergen)
                            @php($assessment = $item->allergenAssessments->first(fn ($candidate) => $candidate->allergen?->code === $allergen->code))
                            @if ($assessment && $assessment->isCurrentFor($item) && $assessment->recipe_status === 'reviewed_absent' && $assessment->cross_contact_status === 'reviewed_no_known_risk')
                                <li class="lm-allergen-evidence">
                                    Reviewed: {{ $allergen->name }} not listed in recipe · No known cross-contact risk
                                </li>
                            @elseif (! $assessment)
                                <li class="lm-allergen-evidence">
                                    Menu check: {{ $allergen->name }} not listed in dish name or description · Confirm with staff
                                </li>
                            @endif
                        @endforeach
                    </ul>
                @endif
            </x-menu-item>
        @empty
            <div class="lm-empty lm-stack items-start">
                <h2 class="lm-h2">No dishes match your preferences.</h2>
                <p class="lm-body lm-muted">Try changing your request or browse the full menu.</p>
                <a href="{{ route('ai.recommendations') }}" class="lm-button lm-button--secondary">Adjust Preferences</a>
            </div>
        @endforelse
        <div class="lm-actions lm-divider pt-(--lm-space-lg) mt-(--lm-space-lg)">
            <a href="{{ route('ai.recommendations') }}" class="lm-button lm-button--secondary">Try Another Request</a>
            <a href="{{ route('menu.index') }}" class="lm-button lm-button--quiet">Continue to Full Menu</a>
        </div>
    </section>
@endsection

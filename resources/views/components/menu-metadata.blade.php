@props(['item', 'detail' => false])

@php
    $metadata = [];
    foreach (['spice_level' => 'Spice', 'protein_type' => 'Protein', 'dietary_type' => 'Dietary', 'flavor_profile' => 'Flavour'] as $field => $label) {
        $value = $item->$field;
        if ($value !== null && $value !== '' && $value !== 'none') {
            $metadata[$label] = \App\Services\Ai\AiRecommendationOptions::label($field, $value);
        }
    }
@endphp

@if ($metadata)
    @if ($detail)
        <dl {{ $attributes->class(['lm-meta grid grid-cols-2 gap-(--lm-space-md)']) }} aria-label="Dish attributes">
            @foreach ($metadata as $label => $value)
                <div class="min-w-0">
                    <dt class="lm-label">{{ $label }}</dt>
                    <dd class="mt-(--lm-space-xs) wrap-anywhere">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    @else
        <ul {{ $attributes->class(['lm-meta flex flex-wrap gap-x-(--lm-space-md) gap-y-(--lm-space-xs)']) }} aria-label="Dish attributes">
            @foreach ($metadata as $label => $value)
                <li>{{ $label }}: {{ $value }}</li>
            @endforeach
        </ul>
    @endif
@endif

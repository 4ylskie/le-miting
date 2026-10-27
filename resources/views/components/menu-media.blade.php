@props(['item', 'eager' => false])

@if ($item->image_path)
    <figure {{ $attributes->class(['lm-media']) }}>
        <img src="{{ asset('storage/' . $item->image_path) }}" alt="{{ $item->name }}" width="1200" height="900" loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async">
    </figure>
@endif

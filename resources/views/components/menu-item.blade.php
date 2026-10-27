@props(['item', 'heading' => 'h2', 'orderable' => true])

<article {{ $attributes->class(['lm-divider grid min-w-0 gap-(--lm-space-md) py-(--lm-space-lg)', 'md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]' => (bool) $item->image_path]) }}>
    <x-menu-media :item="$item" />
    <div class="lm-stack min-w-0">
        <div class="flex flex-col gap-(--lm-space-xs) sm:flex-row sm:items-baseline sm:justify-between sm:gap-(--lm-space-md)">
            <{{ $heading }} class="{{ $heading === 'h3' ? 'lm-h3' : 'lm-h2' }} min-w-0 wrap-anywhere">
                <a href="{{ route('menu.show', $item) }}" class="underline-offset-4 hover:underline">{{ $item->name }}</a>
            </{{ $heading }}>
            <p class="lm-body shrink-0 tabular-nums">RM {{ number_format($item->price, 2) }}</p>
        </div>
        @if ($item->description)
            <p class="lm-body lm-muted max-w-prose wrap-anywhere">{{ $item->description }}</p>
        @endif
        <x-menu-metadata :item="$item" class="lm-muted" />
        {{ $slot }}
        @if (! $item->is_available)
            <p class="lm-status self-start" data-state="unavailable">Currently unavailable</p>
        @endif
        <div class="lm-actions">
            @if ($orderable)
                <form method="POST" action="{{ route('cart.add') }}">
                    @csrf
                    <input type="hidden" name="menu_item_id" value="{{ $item->id }}">
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit" class="lm-button lm-button--secondary" @disabled(! $item->is_available)>
                        Add to Cart<span class="sr-only">: {{ $item->name }}</span>
                    </button>
                </form>
            @endif
            <a href="{{ route('menu.show', $item) }}" class="lm-button lm-button--quiet">
                View Dish<span class="sr-only">: {{ $item->name }}</span>
            </a>
        </div>
    </div>
</article>

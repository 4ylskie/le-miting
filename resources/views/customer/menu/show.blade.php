@extends('layouts.master')

@section('title', $item->name.' — Le Miting')

@section('content')
    <section class="lm-container lm-section" aria-labelledby="dish-heading">
        <nav class="lm-actions lm-meta mb-(--lm-space-xl)" aria-label="Breadcrumb">
            <a href="{{ route('menu.index') }}" class="lm-button lm-button--quiet">Menu</a>
            @if ($item->category)
                <span aria-hidden="true">/</span>
                <a href="{{ route('menu.index', ['category' => $item->category->slug]) }}" class="lm-button lm-button--quiet">{{ $item->category->name }}</a>
            @endif
        </nav>

        @if (session('success'))
            <p class="lm-alert mb-(--lm-space-lg)" data-state="success" role="status">{{ session('success') }}</p>
        @endif
        @if ($errors->any())
            <div class="lm-alert mb-(--lm-space-lg)" data-state="error" role="alert">
                @foreach ($errors->all() as $message)
                    <p>{{ $message }}</p>
                @endforeach
            </div>
        @endif

        <div @class(['grid items-start gap-(--lm-space-xl)', 'lg:grid-cols-2' => (bool) $item->image_path, 'max-w-3xl' => ! $item->image_path])>
            <x-menu-media :item="$item" :eager="true" />
            <div class="lm-stack min-w-0">
                @if ($item->category)
                    <p class="lm-eyebrow wrap-anywhere">{{ $item->category->name }}</p>
                @endif
                <h1 id="dish-heading" class="lm-h1 wrap-anywhere">{{ $item->name }}</h1>
                <p class="lm-h3 tabular-nums">RM {{ number_format($item->price, 2) }}</p>
                @if ($item->description)
                    <p class="lm-body lm-muted wrap-anywhere">{{ $item->description }}</p>
                @endif

                <x-menu-metadata :item="$item" :detail="true" />

                @if ($item->is_available)
                    <form method="POST" action="{{ route('cart.add') }}" class="lm-divider flex flex-wrap items-end gap-(--lm-space-sm) pt-(--lm-space-lg)">
                        @csrf
                        <input type="hidden" name="menu_item_id" value="{{ $item->id }}">
                        <div class="lm-field w-24">
                            <label for="quantity" class="lm-label">Quantity</label>
                            <select id="quantity" name="quantity" class="lm-control" @if ($errors->has('quantity')) aria-invalid="true" aria-describedby="quantity-error" @endif>
                                @for ($i = 1; $i <= 10; $i++)
                                    <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <button type="submit" class="lm-button">Add to Cart</button>
                        @error('quantity')
                            <p id="quantity-error" class="lm-error w-full">{{ $message }}</p>
                        @enderror
                    </form>
                @else
                    <p class="lm-status self-start" data-state="unavailable">This dish is currently unavailable.</p>
                @endif
            </div>
        </div>
    </section>

    @if ($related->isNotEmpty())
        <section class="lm-container lm-section lm-divider" aria-labelledby="related-heading">
            <h2 id="related-heading" class="lm-h2 mb-(--lm-space-lg)">You May Also Like</h2>
            @foreach ($related as $relatedItem)
                <x-menu-item :item="$relatedItem" heading="h3" :orderable="false" />
            @endforeach
        </section>
    @endif
@endsection

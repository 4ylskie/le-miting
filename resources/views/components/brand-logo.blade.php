@props([
    'variant' => 'customer',
    'size' => 'md',
    'mode' => 'lockup',
    'markClass' => '',
    'textClass' => '',
    'label' => 'Lé Miting',
])

@php
    $variant = in_array($variant, ['customer', 'admin', 'auth', 'footer'], true) ? $variant : 'customer';
    $size = in_array($size, ['sm', 'md', 'lg', 'xl'], true) ? $size : 'md';
    $mode = in_array($mode, ['lockup', 'full-image'], true) ? $mode : 'lockup';
@endphp

<span {{ $attributes->class(['lm-brand-lockup', "lm-brand-lockup--{$variant}", "lm-brand-lockup--{$size}", "lm-brand-lockup--{$mode}"]) }}>
    @if ($mode === 'full-image')
        <img src="{{ asset('images/le-miting-logo-full.png') }}" alt="" class="lm-brand-original-full {{ $markClass }}" aria-hidden="true">
    @else
        <img src="{{ asset('images/le-miting-emblem.png') }}" alt="" class="lm-brand-original-emblem {{ $markClass }}" aria-hidden="true">
        <span class="lm-brand-wordmark {{ $textClass }}" aria-hidden="true">{{ $label }}</span>
    @endif
    <span class="sr-only">Le Miting</span>
</span>

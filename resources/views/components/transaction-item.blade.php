@props(['name', 'quantity', 'unitPrice'])

<li {{ $attributes->class(['lm-meta flex flex-col gap-(--lm-space-xs) py-(--lm-space-sm) sm:flex-row sm:justify-between sm:gap-(--lm-space-md)']) }}>
    <span class="min-w-0 wrap-anywhere">{{ $quantity }} × {{ $name }}</span>
    <span class="shrink-0 tabular-nums">RM {{ number_format($unitPrice * $quantity, 2) }}</span>
</li>

@props(['status' => null, 'label' => null, 'color' => null])
@php
    if ($status instanceof \BackedEnum && method_exists($status, 'label')) {
        $label ??= $status->label();
        $color ??= $status->color();
    }
@endphp
<span {{ $attributes->merge(['class' => 'badge badge-soft-'.($color ?? 'secondary')]) }}>{{ $label ?? '—' }}</span>

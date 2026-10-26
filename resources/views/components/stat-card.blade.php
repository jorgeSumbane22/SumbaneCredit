@props(['label', 'value', 'icon' => 'graph-up', 'color' => 'navy', 'hint' => null, 'href' => null])
<div class="card stat-card">
    <div class="card-body d-flex align-items-start gap-3">
        <span class="stat-icon icon-{{ $color }}"><i class="bi bi-{{ $icon }}"></i></span>
        <div class="min-w-0">
            <div class="stat-label">{{ $label }}</div>
            <div class="stat-value">{{ $value }}</div>
            @if ($hint)
                <div class="small text-muted mt-1">{{ $hint }}</div>
            @endif
            @if ($href)
                <a href="{{ $href }}" class="stretched-link"></a>
            @endif
        </div>
    </div>
</div>

@props(['title', 'subtitle' => null, 'breadcrumbs' => []])
<div class="page-header">
    <div>
        @if ($breadcrumbs)
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    @foreach ($breadcrumbs as $label => $url)
                        @if ($url && ! $loop->last)
                            <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                        @else
                            <li class="breadcrumb-item active" aria-current="page">{{ is_int($label) ? $url : $label }}</li>
                        @endif
                    @endforeach
                </ol>
            </nav>
        @endif
        <h1>{{ $title }}</h1>
        @if ($subtitle)
            <p class="subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @if (trim($slot))
        <div class="d-flex flex-wrap gap-2">{{ $slot }}</div>
    @endif
</div>

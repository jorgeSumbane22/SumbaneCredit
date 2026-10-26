@props(['icon' => 'inbox', 'title' => 'Sem registos', 'text' => null])
<div class="empty-state">
    <i class="bi bi-{{ $icon }}"></i>
    <h6>{{ $title }}</h6>
    @if ($text)
        <p class="small mb-2">{{ $text }}</p>
    @endif
    {{ $slot }}
</div>

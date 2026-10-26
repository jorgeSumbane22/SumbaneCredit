{{-- Formulário de acção (POST) com confirmação SweetAlert2 e, opcionalmente, motivo obrigatório. --}}
@props(['action', 'method' => 'POST', 'confirm' => null, 'reason' => null, 'reasonLabel' => 'Motivo', 'button' => 'Confirmar', 'icon' => null, 'class' => 'btn btn-sm btn-outline-primary', 'confirmColor' => null, 'formClass' => 'd-inline'])
<form action="{{ $action }}" method="POST" class="{{ $formClass }}"
      @if ($confirm) data-confirm="{{ $confirm }}" @endif
      @if ($reason) data-reason="{{ $reason }}" data-reason-label="{{ $reasonLabel }}" @endif
      @if ($confirmColor) data-confirm-color="{{ $confirmColor }}" @endif>
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif
    {{ $slot }}
    <button type="submit" class="{{ $class }}">@if ($icon)<i class="bi bi-{{ $icon }} me-1"></i>@endif{{ $button }}</button>
</form>

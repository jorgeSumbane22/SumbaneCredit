@extends('layouts.base')

@section('body')
<div class="auth-wrap">
    <div class="auth-side">
        <a href="{{ route('public.home') }}"><img src="{{ asset('images/logo-white.svg') }}" alt="{{ $site['name'] }}" height="44"></a>
        <div>
            <h2 class="fw-bold mb-3">{{ $site['tagline'] }}</h2>
            <p class="mb-4">Peça crédito online, acompanhe o seu pedido em tempo real e faça a gestão das suas prestações e pagamentos num só lugar.</p>
            <ul class="list-unstyled small">
                <li class="mb-2"><i class="bi bi-shield-check text-success me-2"></i>Dados protegidos e acesso controlado</li>
                <li class="mb-2"><i class="bi bi-calculator text-success me-2"></i>Simulação transparente das condições</li>
                <li class="mb-2"><i class="bi bi-bell text-success me-2"></i>Avisos de vencimento e de pagamentos</li>
            </ul>
        </div>
        <div class="small" style="color:#8ea3c0">&copy; {{ date('Y') }} {{ $site['name'] }}</div>
    </div>
    <div class="auth-main">
        <div class="auth-card">
            <div class="d-lg-none text-center mb-4">
                <a href="{{ route('public.home') }}"><img src="{{ asset('images/logo.svg') }}" alt="{{ $site['name'] }}" height="40"></a>
            </div>
            <x-alerts />
            @if (session('success'))
                <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}</div>
            @endif
            @yield('content')
        </div>
    </div>
</div>
@endsection

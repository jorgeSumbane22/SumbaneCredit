@extends('layouts.base')

@section('body')
@php
    $links = [
        'public.home' => 'Início',
        'public.about' => 'Sobre nós',
        'public.products' => 'Produtos',
        'public.simulator' => 'Simulador',
        'public.how' => 'Como funciona',
        'public.faq' => 'Perguntas',
        'public.contact' => 'Contactos',
    ];
@endphp
<nav class="navbar navbar-expand-lg public-nav sticky-top">
    <div class="container">
        <a class="navbar-brand" href="{{ route('public.home') }}"><img src="{{ asset('images/logo.svg') }}" alt="{{ $site['name'] }}"></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-expanded="false" aria-label="Abrir menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="publicNav">
            <ul class="navbar-nav mx-auto">
                @foreach ($links as $route => $label)
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs($route) ? 'active' : '' }}" href="{{ route($route) }}">{{ $label }}</a></li>
                @endforeach
            </ul>
            <div class="d-flex gap-2 mt-2 mt-lg-0">
                @auth
                    <a href="{{ route('home') }}" class="btn btn-success"><i class="bi bi-grid me-1"></i>A minha área</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline-primary">Entrar</a>
                    <a href="{{ route('register') }}" class="btn btn-success">Criar conta</a>
                @endauth
            </div>
        </div>
    </div>
</nav>

<main>
    @yield('content')
</main>

<footer class="public-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <img src="{{ asset('images/logo-white.svg') }}" alt="{{ $site['name'] }}" height="40" class="mb-3">
                <p class="small">{{ $site['tagline'] }}<br>Sistema Inteligente de Gestão de Microcrédito.</p>
                @if ($site['registration_info'])
                    <p class="small mb-0">{{ $site['registration_info'] }}</p>
                @endif
            </div>
            <div class="col-6 col-lg-2">
                <h6>Navegação</h6>
                <ul class="list-unstyled small">
                    @foreach (array_slice($links, 0, 5, true) as $route => $label)
                        <li class="mb-1"><a href="{{ route($route) }}">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h6>Informação</h6>
                <ul class="list-unstyled small">
                    <li class="mb-1"><a href="{{ route('public.faq') }}">Perguntas frequentes</a></li>
                    <li class="mb-1"><a href="{{ route('public.privacy') }}">Política de privacidade</a></li>
                    <li class="mb-1"><a href="{{ route('public.terms') }}">Termos e condições</a></li>
                    <li class="mb-1"><a href="{{ route('login') }}">Área do cliente</a></li>
                </ul>
            </div>
            <div class="col-lg-4">
                <h6>Contactos</h6>
                <ul class="list-unstyled small">
                    @if ($site['address'])<li class="mb-1"><i class="bi bi-geo-alt me-2"></i>{{ $site['address'] }}</li>@endif
                    @if ($site['phone'])<li class="mb-1"><i class="bi bi-telephone me-2"></i>{{ $site['phone'] }}</li>@endif
                    @if ($site['email'])<li class="mb-1"><i class="bi bi-envelope me-2"></i>{{ $site['email'] }}</li>@endif
                    @if ($site['hours'])<li class="mb-1"><i class="bi bi-clock me-2"></i>{{ $site['hours'] }}</li>@endif
                    @if (! $site['address'] && ! $site['phone'] && ! $site['email'])
                        <li class="mb-1"><a href="{{ route('public.contact') }}">Envie-nos uma mensagem</a></li>
                    @endif
                </ul>
            </div>
        </div>
        <hr style="border-color:rgba(255,255,255,.12)">
        <div class="d-flex flex-wrap justify-content-between small gap-2">
            <span>&copy; {{ date('Y') }} {{ $site['legal_name'] ?: $site['name'] }}. Todos os direitos reservados.</span>
            <span>Valores em Meticais (MZN). As simulações são indicativas e não constituem aprovação de crédito.</span>
        </div>
    </div>
</footer>
@endsection

@extends('layouts.base')

@section('body')
@php
    $menu = [
        ['route' => 'client.dashboard', 'icon' => 'house', 'label' => 'Início', 'match' => 'client.dashboard'],
        ['route' => 'client.applications.index', 'icon' => 'file-earmark-text', 'label' => 'Meus pedidos', 'match' => 'client.applications.*'],
        ['route' => 'client.loans.index', 'icon' => 'briefcase', 'label' => 'Meus empréstimos', 'match' => 'client.loans.*'],
        ['route' => 'client.installments.index', 'icon' => 'calendar3', 'label' => 'Minhas prestações', 'match' => 'client.installments.*'],
        ['route' => 'client.payments.index', 'icon' => 'cash-stack', 'label' => 'Meus pagamentos', 'match' => 'client.payments.*'],
        ['route' => 'client.documents.index', 'icon' => 'folder2-open', 'label' => 'Documentos', 'match' => 'client.documents.*'],
        ['route' => 'notifications.index', 'icon' => 'bell', 'label' => 'Notificações', 'match' => 'notifications.*', 'badge' => $unreadCount ?? 0],
        ['route' => 'client.tickets.index', 'icon' => 'headset', 'label' => 'Suporte', 'match' => 'client.tickets.*'],
        ['route' => 'client.profile.edit', 'icon' => 'person', 'label' => 'Perfil', 'match' => 'client.profile.*'],
        ['route' => 'account.security', 'icon' => 'shield-lock', 'label' => 'Segurança', 'match' => 'account.*'],
    ];
@endphp
<div class="app-shell">
    <aside class="sidebar client-sidebar" aria-label="Menu do cliente">
        <a href="{{ route('client.dashboard') }}" class="brand"><img src="{{ asset('images/logo.svg') }}" alt="{{ $site['name'] }}"></a>
        <nav class="nav-scroll">
            <div class="px-2 pb-2">
                <a href="{{ route('client.applications.create') }}" class="btn btn-success w-100"><i class="bi bi-plus-circle me-1"></i>Pedir crédito</a>
            </div>
            <div class="nav-section">Área do cliente</div>
            @foreach ($menu as $item)
                <a href="{{ route($item['route']) }}" class="nav-link {{ request()->routeIs($item['match']) ? 'active' : '' }}">
                    <i class="bi bi-{{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>
                    @if (! empty($item['badge']))
                        <span class="badge rounded-pill text-bg-danger">{{ $item['badge'] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>
        <div class="p-3 small text-muted border-top">
            <div class="fw-semibold text-navy">Precisa de ajuda?</div>
            @if ($site['phone'])<div><i class="bi bi-telephone me-1"></i>{{ $site['phone'] }}</div>@endif
            <a href="{{ route('client.tickets.create') }}">Abrir pedido de suporte</a>
        </div>
    </aside>
    <div class="sidebar-backdrop"></div>

    <div class="main">
        @include('layouts.partials.topbar')
        <main class="content">
            <x-alerts />
            @yield('content')
        </main>
        <footer class="footer-app d-flex flex-wrap justify-content-between gap-2">
            <span>&copy; {{ date('Y') }} {{ $site['name'] }}</span>
            <span><a href="{{ route('public.privacy') }}">Privacidade</a> · <a href="{{ route('public.terms') }}">Termos</a></span>
        </footer>
    </div>
</div>
@endsection

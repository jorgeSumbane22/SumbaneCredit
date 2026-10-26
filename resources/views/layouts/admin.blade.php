@extends('layouts.base')

@section('body')
@php
    $u = auth()->user();
    $menu = [
        ['section' => 'Geral'],
        ['route' => 'admin.dashboard', 'icon' => 'speedometer2', 'label' => 'Dashboard', 'can' => 'dashboard.admin', 'match' => 'admin.dashboard'],
        ['section' => 'Crédito'],
        ['route' => 'admin.clients.index', 'icon' => 'people', 'label' => 'Clientes', 'can' => 'clients.view', 'match' => 'admin.clients.*'],
        ['route' => 'admin.applications.index', 'icon' => 'file-earmark-text', 'label' => 'Pedidos', 'can' => 'applications.view', 'match' => 'admin.applications.*', 'badge' => $u->can('applications.view') ? \App\Models\LoanApplication::open()->count() : 0],
        ['route' => 'admin.loans.index', 'icon' => 'briefcase', 'label' => 'Empréstimos', 'can' => 'loans.view', 'match' => 'admin.loans.*'],
        ['route' => 'admin.products.index', 'icon' => 'box-seam', 'label' => 'Produtos de crédito', 'can' => 'products.view', 'match' => 'admin.products.*'],
        ['section' => 'Operações financeiras'],
        ['route' => 'admin.disbursements.index', 'icon' => 'send', 'label' => 'Desembolsos', 'can' => 'disbursements.view', 'match' => 'admin.disbursements.*', 'badge' => $u->can('disbursements.view') ? \App\Models\Disbursement::whereIn('status', ['pending', 'authorized'])->count() : 0],
        ['route' => 'admin.payments.index', 'icon' => 'cash-stack', 'label' => 'Pagamentos', 'can' => 'payments.view', 'match' => 'admin.payments.*', 'badge' => $u->can('payments.view') ? \App\Models\Payment::where('status', 'pending')->count() : 0],
        ['route' => 'admin.reconciliation.index', 'icon' => 'arrow-left-right', 'label' => 'Reconciliação', 'can' => 'payments.reconcile', 'match' => 'admin.reconciliation.*'],
        ['route' => 'admin.collections.index', 'icon' => 'telephone-outbound', 'label' => 'Cobranças', 'can' => 'collections.view', 'match' => 'admin.collections.*', 'badge' => $u->can('collections.view') ? \App\Models\CollectionCase::open()->count() : 0],
        ['section' => 'Análise'],
        ['route' => 'admin.reports.index', 'icon' => 'bar-chart-line', 'label' => 'Relatórios', 'can' => 'reports.view', 'match' => 'admin.reports.*'],
        ['route' => 'admin.audit.index', 'icon' => 'journal-check', 'label' => 'Auditoria', 'can' => 'audit.view', 'match' => 'admin.audit.*'],
        ['section' => 'Atendimento'],
        ['route' => 'admin.tickets.index', 'icon' => 'headset', 'label' => 'Suporte', 'can' => 'support.manage', 'match' => 'admin.tickets.*', 'badge' => $u->can('support.manage') ? \App\Models\SupportTicket::where('status', 'open')->count() : 0],
        ['route' => 'admin.messages.index', 'icon' => 'envelope', 'label' => 'Mensagens de contacto', 'can' => 'contact_messages.manage', 'match' => 'admin.messages.*', 'badge' => $u->can('contact_messages.manage') ? \App\Models\ContactMessage::where('status', 'new')->count() : 0],
        ['section' => 'Administração'],
        ['route' => 'admin.staff.index', 'icon' => 'person-badge', 'label' => 'Funcionários', 'can' => 'staff.manage', 'match' => 'admin.staff.*'],
        ['route' => 'admin.roles.index', 'icon' => 'shield-check', 'label' => 'Perfis e permissões', 'can' => 'roles.manage', 'match' => 'admin.roles.*'],
        ['route' => 'admin.assessment-rules.index', 'icon' => 'sliders', 'label' => 'Regras de avaliação', 'can' => ['settings.manage', 'applications.approve'], 'match' => 'admin.assessment-rules.*'],
        ['route' => 'admin.settings.index', 'icon' => 'gear', 'label' => 'Definições', 'can' => ['settings.manage', 'content.manage'], 'match' => 'admin.settings.*'],
        ['route' => 'admin.faqs.index', 'icon' => 'question-circle', 'label' => 'Perguntas frequentes', 'can' => 'content.manage', 'match' => 'admin.faqs.*'],
        ['route' => 'admin.testimonials.index', 'icon' => 'chat-quote', 'label' => 'Testemunhos', 'can' => 'content.manage', 'match' => 'admin.testimonials.*'],
    ];
    // Remove secções sem itens visíveis
    $visible = [];
    foreach ($menu as $item) {
        if (isset($item['section'])) { $visible[] = $item; continue; }
        if ($u->canAny((array) $item['can'])) { $visible[] = $item; }
    }
    $visible = array_values(array_filter($visible, function ($item, $i) use ($visible) {
        return ! isset($item['section']) || (isset($visible[$i + 1]) && ! isset($visible[$i + 1]['section']));
    }, ARRAY_FILTER_USE_BOTH));
@endphp
<div class="app-shell">
    <aside class="sidebar" aria-label="Menu principal">
        <a href="{{ route('admin.dashboard') }}" class="brand"><img src="{{ asset('images/logo-white.svg') }}" alt="{{ $site['name'] }}"></a>
        <nav class="nav-scroll">
            @foreach ($visible as $item)
                @if (isset($item['section']))
                    <div class="nav-section">{{ $item['section'] }}</div>
                @else
                    <a href="{{ route($item['route']) }}" class="nav-link {{ request()->routeIs($item['match']) ? 'active' : '' }}">
                        <i class="bi bi-{{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>
                        @if (! empty($item['badge']))
                            <span class="badge rounded-pill text-bg-warning">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endif
            @endforeach
        </nav>
        <div class="px-3 py-2 small" style="color:#8ea3c0;border-top:1px solid rgba(255,255,255,.08)">
            <i class="bi bi-clock me-1"></i>{{ now()->format('d/m/Y H:i') }} · Maputo
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
            <span>&copy; {{ date('Y') }} {{ $site['name'] }} — Sistema Inteligente de Gestão de Microcrédito</span>
            <span>Valores em Meticais (MZN) · Fuso horário Africa/Maputo</span>
        </footer>
    </div>
</div>
@endsection

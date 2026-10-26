@extends('layouts.client')
@section('title', 'Início')
@push('chart')<script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>@endpush

@section('content')
@php
    $hour = (int) now()->format('H');
    $greeting = $hour < 12 ? 'Bom dia' : ($hour < 18 ? 'Boa tarde' : 'Boa noite');
@endphp
<x-page-header :title="$greeting.', '.\Illuminate\Support\Str::of($client->full_name)->before(' ')" subtitle="Resumo da sua situação de crédito.">
    <a href="{{ route('client.applications.create') }}" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i>Novo pedido</a>
    <a href="{{ route('client.payments.create') }}" class="btn btn-outline-primary"><i class="bi bi-upload me-1"></i>Enviar comprovativo</a>
</x-page-header>

@if ($profileIncomplete)
    <div class="alert alert-info d-flex align-items-center gap-2">
        <i class="bi bi-person-lines-fill fs-4"></i>
        <div>Complete o seu <a href="{{ route('client.profile.edit') }}">perfil</a> e carregue os <a href="{{ route('client.documents.index') }}">documentos</a> para acelerar a análise dos pedidos.</div>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><x-stat-card label="Empréstimos activos" :value="$activeLoansCount" icon="briefcase" color="navy" :href="route('client.loans.index')" /></div>
    <div class="col-sm-6 col-xl-3"><x-stat-card label="Pedidos em análise" :value="$pendingApplications" icon="hourglass-split" color="orange" :href="route('client.applications.index')" /></div>
    <div class="col-sm-6 col-xl-3"><x-stat-card label="Capital em dívida" :value="\App\Support\Money::format($outstandingPrincipal)" icon="wallet2" color="red" :hint="'Total em dívida: '.\App\Support\Money::format($outstandingTotal)" /></div>
    <div class="col-sm-6 col-xl-3"><x-stat-card label="Total pago" :value="\App\Support\Money::format($totalPaid)" icon="check2-circle" color="green" :href="route('client.payments.index')" /></div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header"><h2 class="card-title">Pagamentos dos últimos 12 meses</h2></div>
            <div class="card-body" style="height:280px">
                <canvas data-money data-chart="{{ json_encode(["type" => "bar", "data" => ["labels" => $chart["labels"], "datasets" => [["label" => "Pago", "data" => $chart["values"], "backgroundColor" => "#159A74", "borderRadius" => 6]]], "options" => ["plugins" => ["legend" => ["display" => false]]]]) }}"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">Actividade recente</h2></div>
            <div class="card-body">
                @if ($activity->isEmpty())
                    <x-empty-state icon="clock-history" title="Sem actividade" text="As suas operações aparecerão aqui." />
                @else
                    <ul class="timeline">
                        @foreach ($activity as $a)
                            <li><span class="dot"></span><div class="fw-semibold small"><i class="bi bi-{{ $a['icon'] }} me-1 text-muted"></i>{{ $a['text'] }}</div><div class="meta">{{ $a['at']?->format('d/m/Y H:i') }}</div></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-4 {{ $nextInstallment && $nextInstallment->isOverdue() ? 'border-danger' : '' }}">
            <div class="card-header"><h2 class="card-title">Próxima prestação</h2></div>
            <div class="card-body">
                @if ($nextInstallment)
                    <div class="small-label">{{ $nextInstallment->loan->loan_number }} · Prestação n.º {{ $nextInstallment->number }}</div>
                    <div class="fs-3 fw-bold text-navy tabular">@money($nextInstallment->outstandingTotal())</div>
                    <div class="mb-3">
                        Vencimento: <strong>@date($nextInstallment->due_date)</strong>
                        @if ($nextInstallment->isOverdue())
                            <span class="badge badge-soft-danger ms-1">Em atraso há {{ $nextInstallment->due_date->diffInDays(today()) }} dia(s)</span>
                        @else
                            <span class="text-muted small">({{ $nextInstallment->due_date->diffForHumans() }})</span>
                        @endif
                    </div>
                    <a href="{{ route('client.payments.create', ['emprestimo' => $nextInstallment->loan_id]) }}" class="btn btn-success w-100">Pagar / enviar comprovativo</a>
                @else
                    <x-empty-state icon="calendar-check" title="Sem prestações em aberto" />
                @endif
            </div>
        </div>
        <div class="card">
            <div class="card-header d-flex justify-content-between"><h2 class="card-title">Notificações</h2><a href="{{ route('notifications.index') }}" class="small">Ver todas</a></div>
            <div class="list-group list-group-flush">
                @forelse ($notifications as $n)
                    <a href="{{ route('notifications.open', $n->id) }}" class="list-group-item list-group-item-action {{ $n->read_at ? '' : 'bg-green-soft' }}">
                        <div class="small fw-semibold"><i class="bi bi-{{ $n->data['icon'] ?? 'bell' }} me-1"></i>{{ $n->data['title'] ?? '' }}</div>
                        <div class="small text-muted">{{ $n->created_at->diffForHumans() }}</div>
                    </a>
                @empty
                    <div class="p-3"><x-empty-state icon="bell" title="Sem notificações" /></div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@extends('layouts.admin')
@section('title', 'Dashboard')
@push('chart')<script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>@endpush

@php
    use App\Support\Money;
    $navy = '#142D4E'; $green = '#159A74'; $orange = '#F59E0B'; $red = '#DC3545';
    $palette = [$navy, $green, $orange, $red, '#6366f1', '#64748b'];
@endphp

@section('content')
<x-page-header title="Dashboard" :subtitle="'Olá, '.auth()->user()->name.'. Indicadores calculados a partir dos registos do sistema.'" />

@include('admin.partials.period-filter')

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-stat-card label="Clientes (total)" :value="number_format($m['clients_total'], 0, ',', '.')" icon="people" :hint="$m['clients_active'].' com empréstimo activo · '.$m['clients_new'].' novos no período'" /></div>
    <div class="col-6 col-lg-3"><x-stat-card label="Pedidos pendentes" :value="$m['applications_pending']" icon="hourglass-split" color="orange" :hint="$m['applications_approved'].' aprovados · '.$m['applications_rejected'].' rejeitados (período)'" :href="route('admin.applications.index')" /></div>
    <div class="col-6 col-lg-3"><x-stat-card label="Empréstimos activos" :value="$m['loans_active']" icon="briefcase" color="green" :hint="$m['loans_settled'].' liquidados · '.$m['loans_awaiting'].' por desembolsar'" :href="route('admin.loans.index')" /></div>
    <div class="col-6 col-lg-3"><x-stat-card label="Prestações vencidas" :value="$m['installments_overdue']" icon="exclamation-triangle" color="red" :hint="'PAR: '.str_replace('.', ',', $m['par_ratio']).'% · PAR30: '.str_replace('.', ',', $m['par30_ratio']).'%'" :href="route('admin.collections.index')" /></div>
</div>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><x-stat-card label="Desembolsado (período)" :value="Money::format($m['disbursed'])" icon="send" /></div>
    <div class="col-6 col-lg-3"><x-stat-card label="Capital em dívida" :value="Money::format($m['outstanding_principal'])" icon="wallet2" color="orange" :hint="'Total em dívida: '.Money::format($m['outstanding_total'])" /></div>
    <div class="col-6 col-lg-3"><x-stat-card label="Pagamentos recebidos (período)" :value="Money::format($m['payments_received'])" icon="cash-stack" color="green" :hint="'Juros recebidos: '.Money::format($m['interest_received'])" /></div>
    <div class="col-6 col-lg-3"><x-stat-card label="Carteira em atraso" :value="Money::format($m['portfolio_at_risk'])" icon="graph-down-arrow" color="red" :hint="$m['recovery_rate'] !== null ? 'Recuperação do período: '.str_replace('.', ',', $m['recovery_rate']).'%' : 'Sem prestações vencidas no período'" /></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="card h-100"><div class="card-header"><h2 class="card-title">Desembolsos e pagamentos por mês</h2></div>
            <div class="card-body" style="height:320px">
                <canvas data-money data-chart="{{ json_encode(["type" => "bar", "data" => ["labels" => $charts["labels"], "datasets" => [
                    ["label" => "Desembolsos", "data" => $charts["disbursed"], "backgroundColor" => $navy, "borderRadius" => 5],
                    ["label" => "Pagamentos", "data" => $charts["payments"], "backgroundColor" => $green, "borderRadius" => 5],
                ]]]) }}"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card h-100"><div class="card-header"><h2 class="card-title">Distribuição dos empréstimos</h2></div>
            <div class="card-body" style="height:320px">
                @if (array_sum($charts['status_values']) > 0)
                    <canvas data-chart="{{ json_encode(["type" => "doughnut", "data" => ["labels" => $charts["status_labels"], "datasets" => [["data" => $charts["status_values"], "backgroundColor" => $palette]]], "options" => ["plugins" => ["legend" => ["position" => "bottom"]]]]) }}"></canvas>
                @else
                    <x-empty-state icon="pie-chart" title="Sem empréstimos" />
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-5">
        <div class="card h-100"><div class="card-header"><h2 class="card-title">Evolução da carteira (capital em dívida)</h2></div>
            <div class="card-body" style="height:280px">
                <canvas data-money data-chart="{{ json_encode(["type" => "line", "data" => ["labels" => $charts["labels"], "datasets" => [["label" => "Carteira", "data" => $charts["portfolio"], "borderColor" => $navy, "backgroundColor" => "rgba(20,45,78,.08)", "fill" => true, "tension" => 0.3]]], "options" => ["plugins" => ["legend" => ["display" => false]]]]) }}"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card h-100"><div class="card-header"><h2 class="card-title">Prestações pagas/por pagar com atraso</h2></div>
            <div class="card-body" style="height:280px">
                <canvas data-chart="{{ json_encode(["type" => "line", "data" => ["labels" => $charts["labels"], "datasets" => [["label" => "Prestações com atraso (por mês de vencimento)", "data" => $charts["late"], "borderColor" => $red, "backgroundColor" => "rgba(220,53,69,.1)", "fill" => true, "tension" => 0.3]]], "options" => ["plugins" => ["legend" => ["display" => false]], "scales" => ["y" => ["beginAtZero" => true, "ticks" => ["precision" => 0]]]]]) }}"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-3">
        <div class="card h-100"><div class="card-header"><h2 class="card-title">Produtos mais utilizados</h2></div>
            <div class="card-body" style="height:280px">
                @if ($charts['product_values'])
                    <canvas data-chart="{{ json_encode(["type" => "bar", "data" => ["labels" => $charts["product_labels"], "datasets" => [["label" => "Empréstimos", "data" => $charts["product_values"], "backgroundColor" => $green, "borderRadius" => 5]]], "options" => ["indexAxis" => "y", "plugins" => ["legend" => ["display" => false]], "scales" => ["x" => ["ticks" => ["precision" => 0]]]]]) }}"></canvas>
                @else
                    <x-empty-state icon="box" title="Sem dados" />
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-6">
        <div class="card h-100"><div class="card-header d-flex justify-content-between"><h2 class="card-title">Pedidos a aguardar análise</h2><a href="{{ route('admin.applications.index') }}" class="small">Ver todos</a></div>
            <div class="table-responsive"><table class="table table-sm table-hover">
                <tbody>
                @forelse ($work['applications'] as $a)
                    <tr><td><a href="{{ route('admin.applications.show', $a) }}" class="fw-semibold">{{ $a->application_number }}</a><div class="small text-muted">{{ $a->client->full_name }}</div></td>
                        <td class="num">@money($a->requested_amount)</td><td><x-badge :status="$a->status" /></td><td class="small text-muted">{{ $a->submitted_at?->diffForHumans() }}</td></tr>
                @empty
                    <tr><td><x-empty-state icon="check2-all" title="Sem pedidos pendentes" /></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card h-100"><div class="card-header d-flex justify-content-between"><h2 class="card-title">Pagamentos por confirmar</h2><a href="{{ route('admin.payments.index', ['estado' => 'pending']) }}" class="small">Ver todos</a></div>
            <div class="table-responsive"><table class="table table-sm table-hover">
                <tbody>
                @forelse ($work['payments'] as $p)
                    <tr><td><a href="{{ route('admin.payments.show', $p) }}" class="fw-semibold">{{ $p->payment_number }}</a><div class="small text-muted">{{ $p->client->full_name }} · {{ $p->loan->loan_number }}</div></td>
                        <td class="num">@money($p->amount)</td><td class="small">{{ $p->method->label() }}</td><td class="small text-muted">{{ $p->sourceLabel() }}</td></tr>
                @empty
                    <tr><td><x-empty-state icon="check2-all" title="Sem pagamentos pendentes" /></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card h-100"><div class="card-header d-flex justify-content-between"><h2 class="card-title">Desembolsos em curso</h2><a href="{{ route('admin.disbursements.index') }}" class="small">Ver todos</a></div>
            <div class="table-responsive"><table class="table table-sm table-hover">
                <tbody>
                @forelse ($work['disbursements'] as $d)
                    <tr><td><a href="{{ route('admin.disbursements.show', $d) }}" class="fw-semibold">{{ $d->disbursement_number }}</a><div class="small text-muted">{{ $d->loan->client->full_name }}</div></td>
                        <td class="num">@money($d->net_amount)</td><td><x-badge :status="$d->status" /></td></tr>
                @empty
                    <tr><td><x-empty-state icon="check2-all" title="Sem desembolsos pendentes" /></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card h-100"><div class="card-header d-flex justify-content-between"><h2 class="card-title">Maiores atrasos</h2><a href="{{ route('admin.collections.index') }}" class="small">Cobranças</a></div>
            <div class="table-responsive"><table class="table table-sm table-hover">
                <tbody>
                @forelse ($work['overdue'] as $l)
                    <tr><td><a href="{{ route('admin.loans.show', $l) }}" class="fw-semibold">{{ $l->loan_number }}</a><div class="small text-muted">{{ $l->client->full_name }}</div></td>
                        <td class="num">@money($l->total_outstanding)</td><td><span class="badge badge-soft-danger">{{ $l->days_past_due }} dias</span></td></tr>
                @empty
                    <tr><td><x-empty-state icon="emoji-smile" title="Sem empréstimos em atraso" /></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection

@extends('layouts.client')
@section('title', 'Empréstimo '.$loan->loan_number)

@section('content')
<x-page-header :title="'Empréstimo '.$loan->loan_number" :subtitle="$loan->product->name.' · Contrato '.$loan->contract_number" :breadcrumbs="['Início' => route('client.dashboard'), 'Empréstimos' => route('client.loans.index'), $loan->loan_number => null]">
    <x-badge :status="$loan->status" class="fs-6" />
    <a href="{{ route('client.loans.contract', $loan) }}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1"></i>{{ $loan->status->value === 'awaiting_contract' ? 'Minuta do contrato' : 'Contrato' }}</a>
    <a href="{{ route('client.loans.schedule', $loan) }}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-calendar3 me-1"></i>Plano (PDF)</a>
    @if (in_array($loan->status->value, ['active', 'settled'], true))
        <a href="{{ route('client.loans.statement', $loan) }}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-journal-text me-1"></i>Extracto</a>
    @endif
    @if ($loan->isActive())
        <a href="{{ route('client.payments.create', ['emprestimo' => $loan->id]) }}" class="btn btn-success"><i class="bi bi-upload me-1"></i>Enviar comprovativo</a>
    @endif
</x-page-header>

@if ($loan->status->value === 'awaiting_contract')
    <div class="alert alert-info"><i class="bi bi-info-circle me-1"></i>O seu pedido foi aprovado. Consulte a minuta do contrato e dirija-se à instituição para a assinatura. O desembolso é efectuado após a formalização.</div>
@elseif ($loan->status->value === 'contracted')
    <div class="alert alert-info"><i class="bi bi-hourglass-split me-1"></i>Contrato formalizado. O desembolso está a ser processado; será notificado quando estiver concluído.</div>
@elseif ($loan->days_past_due > 0)
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i>Tem prestações em atraso há {{ $loan->days_past_due }} dia(s). Regularize ou contacte-nos para encontrar uma solução.</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><x-stat-card label="Montante contratado" :value="\App\Support\Money::format($loan->principal_amount)" icon="cash" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Total contratado" :value="\App\Support\Money::format($loan->total_contract_amount)" icon="calculator" color="orange" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Total pago" :value="\App\Support\Money::format($loan->total_paid)" icon="check2-circle" color="green" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Saldo em dívida" :value="\App\Support\Money::format($loan->total_outstanding)" icon="wallet2" color="red" :hint="$next ? 'Próxima: '.\App\Support\Money::format($next->outstandingTotal()).' a '.$next->due_date->format('d/m/Y') : null" /></div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card mb-4"><div class="card-header"><h2 class="card-title">Condições</h2></div>
            <div class="card-body">
                <dl class="dl-grid small">
                    <dt>Taxa</dt><dd>{{ $loan->rateLabel() }}</dd>
                    <dt>Prazo</dt><dd>{{ $loan->term }} prestações ({{ $loan->repayment_frequency->label() }})</dd>
                    <dt>Método</dt><dd>{{ $loan->amortization_method->shortLabel() }}</dd>
                    <dt>Encargos</dt><dd>{{ $loan->terms_snapshot['fees_label'] ?? '—' }}</dd>
                    <dt>Total de juros</dt><dd>@money($loan->total_interest)</dd>
                    <dt>Desembolso</dt><dd>@date($loan->disbursed_at)</dd>
                    <dt>Última prestação</dt><dd>@date($loan->maturity_date)</dd>
                    <dt>Atrasos</dt><dd>{{ $loan->terms_snapshot['penalty_label'] ?? '—' }}</dd>
                    <dt>Liquidação antecipada</dt><dd>{{ $loan->terms_snapshot['early_settlement_label'] ?? '—' }}</dd>
                </dl>
            </div>
        </div>
        <div class="card"><div class="card-header"><h2 class="card-title">Pagamentos recentes</h2></div>
            <ul class="list-group list-group-flush">
                @forelse ($payments as $p)
                    <a href="{{ route('client.payments.show', $p) }}" class="list-group-item list-group-item-action d-flex justify-content-between">
                        <span class="small">@date($p->paid_at)<br><span class="text-muted">{{ $p->method->label() }}</span></span>
                        <span class="text-end"><strong class="tabular">@money($p->amount)</strong><br><x-badge :status="$p->status" /></span>
                    </a>
                @empty
                    <li class="list-group-item"><x-empty-state icon="cash" title="Sem pagamentos" /></li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Calendário de amortização</h2></div>
            @if ($schedule)
                <div class="card-body pb-0"><div class="alert alert-light border small">Plano previsional (as datas definitivas são fixadas na data do desembolso).</div></div>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>N.º</th><th>Vencimento previsto</th><th class="num">Capital</th><th class="num">Juros</th><th class="num">Encargos</th><th class="num">Prestação</th></tr></thead>
                        <tbody>
                        @foreach ($schedule['rows'] as $r)
                            <tr><td>{{ $r['number'] }}</td><td>{{ \Carbon\Carbon::parse($r['due_date'])->format('d/m/Y') }}</td><td class="num">{{ \App\Support\Money::format($r['principal'], false) }}</td><td class="num">{{ \App\Support\Money::format($r['interest'], false) }}</td><td class="num">{{ \App\Support\Money::format($r['fee'], false) }}</td><td class="num fw-semibold">{{ \App\Support\Money::format($r['total'], false) }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                @include('loans.partials.installments-table', ['installments' => $installments])
            @endif
        </div>
    </div>
</div>
@endsection

@extends('layouts.admin')
@section('title', 'Empréstimo '.$loan->loan_number)
@php use App\Support\Money; $st = $loan->status->value; $activeD = $loan->disbursements->first(fn ($d) => $d->status->value !== 'cancelled'); @endphp

@section('content')
<x-page-header :title="'Empréstimo '.$loan->loan_number" :subtitle="$loan->client->full_name.' · '.$loan->product->name.' · Contrato '.$loan->contract_number" :breadcrumbs="['Empréstimos' => route('admin.loans.index'), $loan->loan_number => null]">
    <x-badge :status="$loan->status" class="fs-6" />
    @if ($loan->days_past_due > 0)<span class="badge badge-soft-danger fs-6">{{ $loan->days_past_due }} dias de atraso</span>@endif
    <div class="dropdown">
        <button class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-file-earmark-pdf me-1"></i>Documentos</button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" target="_blank" href="{{ route('admin.loans.contract', $loan) }}">{{ $st === 'awaiting_contract' ? 'Minuta do contrato' : 'Contrato' }}</a></li>
            <li><a class="dropdown-item" target="_blank" href="{{ route('admin.loans.schedule', $loan) }}">Calendário de amortização</a></li>
            <li><a class="dropdown-item" target="_blank" href="{{ route('admin.loans.statement', $loan) }}">Extracto</a></li>
            @if ($loan->signed_contract_path)<li><a class="dropdown-item" href="{{ route('admin.loans.signed-contract', $loan) }}">Contrato assinado (ficheiro)</a></li>@endif
        </ul>
    </div>
</x-page-header>

{{-- Próximo passo do fluxo --}}
<div class="card mb-4 border-0 bg-navy-soft"><div class="card-body d-flex flex-wrap gap-2 align-items-center">
    <span class="small-label me-2">Próximo passo</span>
    @if ($st === 'awaiting_contract')
        <span class="me-2">Formalizar o contrato com o cliente.</span>
        @can('loans.formalize')<button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#mFormalize"><i class="bi bi-pen me-1"></i>Formalizar contrato</button>@endcan
    @elseif ($st === 'contracted')
        @if ($activeD)
            <span class="me-2">Desembolso {{ $activeD->disbursement_number }} — {{ $activeD->status->label() }}.</span>
            <a href="{{ route('admin.disbursements.show', $activeD) }}" class="btn btn-sm btn-primary">Abrir desembolso</a>
        @else
            <span class="me-2">Registar o pedido de desembolso.</span>
            @can('disbursements.request')<button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#mDisburse"><i class="bi bi-send me-1"></i>Registar desembolso</button>@endcan
            @if ($loan->client->kyc_status !== 'verified')<span class="badge badge-soft-warning">Identificação do cliente por verificar</span>@endif
        @endif
    @elseif ($st === 'active')
        @can('payments.register')
            <a href="{{ route('admin.payments.create', ['emprestimo' => $loan->id]) }}" class="btn btn-sm btn-success"><i class="bi bi-cash me-1"></i>Registar pagamento</a>
            <a href="{{ route('admin.loans.settlement', $loan) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-flag me-1"></i>Liquidação antecipada</a>
        @endcan
        @can('loans.restructure')<button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#mRestructure"><i class="bi bi-arrow-repeat me-1"></i>Reestruturar</button>@endcan
    @elseif ($st === 'settled')
        <span><i class="bi bi-check-circle text-success me-1"></i>Liquidado em @datetime($loan->settled_at).</span>
    @else
        <span>Empréstimo cancelado.</span>
    @endif
    <div class="ms-auto d-flex gap-2">
        @can('payments.reconcile')
            @if (in_array($st, ['active', 'settled'], true))<x-action-form :action="route('admin.loans.reconcile', $loan)" button="Reconciliar" icon="arrow-left-right" class="btn btn-sm btn-outline-secondary" />@endif
        @endcan
        @can('loans.cancel')
            @if (in_array($st, ['awaiting_contract', 'contracted'], true))
                <x-action-form :action="route('admin.loans.cancel', $loan)" confirm="Cancelar este empréstimo? Só é possível antes do desembolso." reason="reason" button="Cancelar" class="btn btn-sm btn-outline-danger" confirm-color="#DC3545" />
            @endif
        @endcan
    </div>
</div></div>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><x-stat-card label="Capital" :value="Money::format($loan->principal_amount)" icon="cash" :hint="'Líquido: '.Money::format(Money::sub($loan->principal_amount, $loan->upfront_fees))" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Total contratado" :value="Money::format($loan->total_contract_amount)" icon="calculator" color="orange" :hint="'Juros: '.Money::format($loan->total_interest)" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Total pago" :value="Money::format($loan->total_paid)" icon="check2-circle" color="green" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Saldo em dívida" :value="Money::format($loan->total_outstanding)" icon="wallet2" color="red" :hint="'Capital: '.Money::format($loan->principal_outstanding)" /></div>
</div>

<ul class="nav nav-tabs mb-3">
    @foreach (['plano' => 'Prestações', 'condicoes' => 'Condições', 'pagamentos' => 'Pagamentos ('.$loan->payments->count().')', 'razao' => 'Movimentos', 'garantias' => 'Garantias', 'historico' => 'Histórico'] as $id => $label)
        <li class="nav-item"><button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#t-{{ $id }}" type="button">{{ $label }}</button></li>
    @endforeach
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="t-plano">
        <div class="card mb-4">
            <div class="card-header"><h2 class="card-title">Plano em vigor @if ($loan->schedule_version > 1)(v{{ $loan->schedule_version }} — reestruturado)@endif</h2></div>
            @if ($preview)
                <div class="card-body pb-0"><div class="alert alert-light border small">Plano previsional; as prestações são criadas na confirmação do desembolso.</div></div>
                <div class="table-responsive"><table class="table table-sm">
                    <thead><tr><th>N.º</th><th>Vencimento previsto</th><th class="num">Capital</th><th class="num">Juros</th><th class="num">Encargos</th><th class="num">Prestação</th></tr></thead>
                    <tbody>@foreach ($preview['rows'] as $r)<tr><td>{{ $r['number'] }}</td><td>{{ \Carbon\Carbon::parse($r['due_date'])->format('d/m/Y') }}</td><td class="num">{{ Money::format($r['principal'], false) }}</td><td class="num">{{ Money::format($r['interest'], false) }}</td><td class="num">{{ Money::format($r['fee'], false) }}</td><td class="num">{{ Money::format($r['total'], false) }}</td></tr>@endforeach</tbody>
                </table></div>
            @else
                @include('loans.partials.installments-table', ['installments' => $installments])
            @endif
        </div>
        @if ($penaltiesList->isNotEmpty())
            <div class="card mb-4"><div class="card-header"><h2 class="card-title">Penalizações</h2></div>
                <div class="table-responsive"><table class="table table-sm">
                    <thead><tr><th>Prestação</th><th>Aplicada</th><th class="num">Valor</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($penaltiesList as $pen)
                        <tr><td>N.º {{ $pen->installment->number }}</td><td>@date($pen->applied_on)</td><td class="num">@money($pen->amount)</td>
                            <td>{{ $pen->status === 'waived' ? 'Dispensada por '.$pen->waiver?->name.' — '.$pen->waiver_reason : 'Aplicada' }}</td>
                            <td class="text-end">@can('penalties.waive')@if ($pen->status === 'applied')<x-action-form :action="route('admin.penalties.waive', $pen)" confirm="Dispensar esta penalização?" reason="reason" button="Dispensar" class="btn btn-sm btn-outline-warning" />@endif @endcan</td></tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
        @endif
        @if ($oldInstallments->isNotEmpty())
            <details class="mb-4"><summary class="small">Planos anteriores ({{ $oldInstallments->count() }} prestações)</summary>
                <div class="card mt-2">@include('loans.partials.installments-table', ['installments' => $oldInstallments])</div>
            </details>
        @endif
    </div>

    <div class="tab-pane fade" id="t-condicoes">
        <div class="row g-4">
            <div class="col-lg-6"><div class="card"><div class="card-body">
                <dl class="dl-grid small">
                    <dt>Produto / versão</dt><dd>{{ $loan->product->name }} · v{{ $loan->productVersion->version }}</dd>
                    <dt>Taxa</dt><dd>{{ $loan->rateLabel() }} ({{ str_replace('.', ',', Money::round(Money::mul($loan->rate_per_period, '100'), 4)) }}% por período)</dd>
                    <dt>Método</dt><dd>{{ $loan->amortization_method->label() }}</dd>
                    <dt>Prestações</dt><dd>{{ $loan->term }} · {{ $loan->repayment_frequency->label() }}</dd>
                    <dt>Encargos</dt><dd>{{ $loan->terms_snapshot['fees_label'] ?? '—' }}</dd>
                    <dt>Encargos no desembolso</dt><dd>@money($loan->upfront_fees)</dd>
                    <dt>Encargos financiados</dt><dd>@money($loan->financed_fees)</dd>
                    <dt>Mora</dt><dd>{{ $loan->terms_snapshot['penalty_label'] ?? '—' }}</dd>
                    <dt>Liquidação antecipada</dt><dd>{{ $loan->terms_snapshot['early_settlement_label'] ?? '—' }}</dd>
                </dl>
            </div></div></div>
            <div class="col-lg-6"><div class="card"><div class="card-body">
                <dl class="dl-grid small">
                    <dt>Pedido</dt><dd><a href="{{ route('admin.applications.show', $loan->application) }}">{{ $loan->application->application_number }}</a></dd>
                    <dt>Aprovado por</dt><dd>{{ $loan->approver?->name }} em @datetime($loan->approved_at)</dd>
                    <dt>Contrato assinado</dt><dd>@datetime($loan->contract_signed_at)</dd>
                    <dt>Desembolso</dt><dd>@date($loan->disbursed_at)</dd>
                    <dt>1.ª prestação</dt><dd>@date($loan->first_due_date)</dd>
                    <dt>Maturidade</dt><dd>@date($loan->maturity_date)</dd>
                    <dt>Agente</dt><dd>{{ $loan->officer?->name ?? '—' }}</dd>
                    <dt>Cliente</dt><dd><a href="{{ route('admin.clients.show', $loan->client) }}">{{ $loan->client->full_name }}</a> · {{ $loan->client->phone }}</dd>
                </dl>
                @if ($reconciliation)
                    <div class="alert {{ $reconciliation['matched'] ? 'alert-success' : 'alert-danger' }} small mb-0 mt-2">
                        <i class="bi bi-{{ $reconciliation['matched'] ? 'check-circle' : 'exclamation-triangle' }} me-1"></i>
                        {{ $reconciliation['matched'] ? 'Saldos reconciliados com as prestações e o razão.' : 'Existem diferenças de reconciliação.' }}
                    </div>
                @endif
            </div></div></div>
        </div>
    </div>

    <div class="tab-pane fade" id="t-pagamentos">
        <div class="card"><div class="table-responsive"><table class="table table-hover table-sm">
            <thead><tr><th>N.º</th><th>Data</th><th>Método</th><th>Referência</th><th>Tipo</th><th class="num">Valor</th><th>Estado</th><th>Confirmado por</th></tr></thead>
            <tbody>
            @forelse ($loan->payments as $p)
                <tr><td><a href="{{ route('admin.payments.show', $p) }}">{{ $p->payment_number }}</a></td><td>@date($p->paid_at)</td><td>{{ $p->method->label() }}</td><td class="small">{{ $p->reference ?? '—' }}</td><td class="small">{{ $p->typeLabel() }}</td><td class="num">@money($p->amount)</td><td><x-badge :status="$p->status" /></td><td class="small">{{ $p->confirmer?->name }}</td></tr>
            @empty
                <tr><td colspan="8"><x-empty-state icon="cash" title="Sem pagamentos" /></td></tr>
            @endforelse
            </tbody>
        </table></div></div>
    </div>

    <div class="tab-pane fade" id="t-razao">
        <div class="card"><div class="table-responsive"><table class="table table-sm table-hover">
            <thead><tr><th>Data</th><th>N.º</th><th>Tipo</th><th>Componente</th><th class="num">Débito</th><th class="num">Crédito</th><th>Descrição</th><th>Utilizador</th></tr></thead>
            <tbody>
            @forelse ($transactions as $t)
                <tr><td class="small text-nowrap">@datetime($t->posted_at)</td><td class="small">{{ $t->transaction_number }}</td><td class="small">{{ $t->typeLabel() }}</td><td class="small">{{ $t->componentLabel() }}</td>
                    <td class="num">{{ $t->direction === 'debit' ? Money::format($t->amount, false) : '' }}</td><td class="num">{{ $t->direction === 'credit' ? Money::format($t->amount, false) : '' }}</td>
                    <td class="small">{{ $t->description }}</td><td class="small">{{ $t->creator?->name ?? 'Sistema' }}</td></tr>
            @empty
                <tr><td colspan="8"><x-empty-state icon="journal" title="Sem movimentos" text="Os movimentos são criados no desembolso." /></td></tr>
            @endforelse
            </tbody>
        </table></div></div>
    </div>

    <div class="tab-pane fade" id="t-garantias">
        <div class="card"><div class="card-body small">
            @forelse ($loan->guarantors as $g)<div class="mb-2"><i class="bi bi-person me-1"></i><strong>{{ $g->full_name }}</strong> — {{ $g->document_number }} · {{ $g->phone }} · {{ $g->relationship }} ({{ $g->status }})</div>@empty<div class="text-muted">Sem fiadores.</div>@endforelse
            <hr>
            @forelse ($loan->collaterals as $c)<div class="mb-2"><i class="bi bi-shield me-1"></i><strong>{{ $c->typeLabel() }}</strong>: {{ $c->description }} — @money($c->estimated_value) ({{ $c->statusLabel() }})</div>@empty<div class="text-muted">Sem garantias.</div>@endforelse
        </div></div>
    </div>

    <div class="tab-pane fade" id="t-historico">
        <div class="row g-4">
            <div class="col-lg-6"><div class="card"><div class="card-header"><h2 class="card-title">Estados</h2></div><div class="card-body">
                <ul class="timeline">
                    @foreach ($loan->statusHistory as $h)
                        <li><span class="dot"></span><div class="fw-semibold small">{{ $h->fromLabel() }} → {{ $h->toLabel() }}</div><div class="small">{{ $h->reason }}</div><div class="meta">@datetime($h->created_at) · {{ $h->user?->name ?? 'Sistema' }}</div></li>
                    @endforeach
                </ul>
            </div></div></div>
            <div class="col-lg-6">
                <div class="card mb-3"><div class="card-header"><h2 class="card-title">Desembolsos</h2></div>
                    <ul class="list-group list-group-flush small">
                        @forelse ($loan->disbursements as $d)<li class="list-group-item"><a href="{{ route('admin.disbursements.show', $d) }}">{{ $d->disbursement_number }}</a> — @money($d->net_amount) — {{ $d->status->label() }}</li>@empty<li class="list-group-item text-muted">Sem desembolsos.</li>@endforelse
                    </ul>
                </div>
                <div class="card"><div class="card-header"><h2 class="card-title">Reestruturações</h2></div>
                    <ul class="list-group list-group-flush small">
                        @forelse ($loan->restructurings as $r)<li class="list-group-item">@datetime($r->created_at) — v{{ $r->previous_schedule_version }} → v{{ $r->new_schedule_version }}: @money($r->new_principal) em {{ $r->new_term }} prestações (capitalizado @money($r->capitalized_amount)). {{ $r->reason }} — {{ $r->approver?->name }}</li>@empty<li class="list-group-item text-muted">Sem reestruturações.</li>@endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modais --}}
@can('loans.formalize')
<div class="modal fade" id="mFormalize" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.loans.formalize', $loan) }}" enctype="multipart/form-data" data-confirm="Confirma que o contrato foi assinado pelas partes?">@csrf
    <div class="modal-header"><h5 class="modal-title">Formalizar contrato</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <x-input name="contract_signed_at" type="date" label="Data da assinatura" :value="today()->toDateString()" max="{{ today()->toDateString() }}" required />
        <x-input name="signed_contract" type="file" label="Contrato assinado digitalizado (opcional)" accept=".pdf,.jpg,.jpeg,.png" />
        <x-textarea name="notes" label="Observações" rows="2" />
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-link" data-bs-dismiss="modal">Fechar</button><button class="btn btn-success">Formalizar</button></div>
</form></div></div>
@endcan

@can('disbursements.request')
<div class="modal fade" id="mDisburse" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.loans.disbursements.store', $loan) }}">@csrf
    <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
    <div class="modal-header"><h5 class="modal-title">Registar desembolso</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <dl class="dl-grid small mb-3"><dt>Capital</dt><dd>@money($loan->principal_amount)</dd><dt>Encargos deduzidos</dt><dd>@money($loan->upfront_fees)</dd><dt>Valor líquido</dt><dd class="fw-bold">@money(Money::sub($loan->principal_amount, $loan->upfront_fees))</dd></dl>
        <x-select name="method" label="Método" :options="\App\Enums\PaymentMethod::options()" required />
        <x-input name="destination_account" label="N.º M-Pesa/e-Mola ou conta bancária (NIB) de destino" :value="$loan->client->phone" />
        <x-textarea name="notes" label="Observações" rows="2" />
        <p class="small text-muted mb-0">O desembolso tem de ser autorizado por outro utilizador e só fica efectivo após a confirmação da execução.</p>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-link" data-bs-dismiss="modal">Fechar</button><button class="btn btn-success">Registar</button></div>
</form></div></div>
@endcan

@can('loans.restructure')
<div class="modal fade" id="mRestructure" tabindex="-1"><div class="modal-dialog modal-lg"><form class="modal-content" method="POST" action="{{ route('admin.loans.restructure', $loan) }}" data-confirm="Confirma a reestruturação? As prestações em aberto serão substituídas por um novo plano.">@csrf
    <div class="modal-header"><h5 class="modal-title">Reestruturação autorizada</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <p class="small">Capital em dívida actual: <strong>@money($loan->principal_outstanding)</strong>. O novo plano é calculado sobre este capital (e, opcionalmente, sobre juros/encargos/penalizações vencidos).</p>
        <div class="row">
            <x-input name="term" type="number" label="Novo n.º de prestações" required col="col-md-4 mb-3" min="1" />
            <x-input name="interest_rate" type="number" step="0.0001" min="0" :label="'Taxa ('.($loan->rate_period === 'monthly' ? 'mensal' : 'anual').', %)'" :value="$loan->interest_rate" required col="col-md-4 mb-3" />
            <x-input name="start_date" type="date" label="Início do novo plano" :value="today()->toDateString()" required col="col-md-4 mb-3" />
            <x-select name="amortization_method" label="Método" :options="\App\Enums\AmortizationMethod::options()" :value="$loan->amortization_method" col="col-md-6 mb-3" />
            <x-select name="repayment_frequency" label="Periodicidade" :options="\App\Enums\RepaymentFrequency::options()" :value="$loan->repayment_frequency" col="col-md-6 mb-3" />
        </div>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="capitalize_overdue" value="1" id="cap"><label class="form-check-label" for="cap">Capitalizar juros, encargos e penalizações vencidos</label></div>
        <x-textarea name="reason" label="Fundamentação (obrigatória)" rows="3" required />
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-link" data-bs-dismiss="modal">Fechar</button><button class="btn btn-warning">Reestruturar</button></div>
</form></div></div>
@endcan
@endsection

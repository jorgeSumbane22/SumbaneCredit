@extends('layouts.admin')
@section('title', 'Registar pagamento')

@section('content')
<x-page-header title="Registar pagamento" subtitle="Registo manual de pagamentos recebidos (M-Pesa, e-Mola, banco ou numerário)." :breadcrumbs="['Pagamentos' => route('admin.payments.index'), 'Registar' => null]" />

@if (! $loan)
    <div class="card" style="max-width:820px"><div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col"><x-input name="q" label="Procurar empréstimo activo" :value="request('q')" placeholder="N.º do empréstimo, nome, documento ou telefone do cliente" col="mb-0" required /></div>
            <div class="col-auto"><button class="btn btn-primary" data-no-loading><i class="bi bi-search"></i></button></div>
        </form>
        @if (request()->filled('q'))
            <div class="list-group mt-3">
                @forelse ($results as $r)
                    <a href="{{ route('admin.payments.create', ['emprestimo' => $r->id]) }}" class="list-group-item list-group-item-action d-flex justify-content-between">
                        <span><strong>{{ $r->loan_number }}</strong> — {{ $r->client->full_name }} <span class="text-muted small">({{ $r->client->phone }})</span></span>
                        <span>Em dívida: @money($r->total_outstanding)</span>
                    </a>
                @empty
                    <div class="text-muted small p-2">Nenhum empréstimo activo encontrado.</div>
                @endforelse
            </div>
        @endif
    </div></div>
@else
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            <form method="POST" action="{{ route('admin.payments.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="loan_id" value="{{ $loan->id }}">
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                <div class="row">
                    <x-input name="amount" type="number" step="0.01" min="0.01" :max="$loan->total_outstanding" label="Valor recebido (MZN)" :value="$next?->outstandingTotal()" required col="col-md-6 mb-3" />
                    <x-input name="paid_at" type="date" label="Data do pagamento" :value="today()->toDateString()" max="{{ today()->toDateString() }}" required col="col-md-6 mb-3" />
                    <x-select name="method" label="Método" :options="\App\Enums\PaymentMethod::options()" required col="col-md-6 mb-3" />
                    <x-input name="reference" label="Referência da operação" col="col-md-6 mb-3" help="Obrigatória excepto numerário. Única por método." />
                    <x-input name="payer_phone" label="Telefone do pagador" col="col-md-6 mb-3" />
                    <x-input name="proof" type="file" label="Comprovativo (opcional)" accept=".pdf,.jpg,.jpeg,.png,.webp" col="col-md-6 mb-3" />
                </div>
                <x-textarea name="notes" label="Observações" rows="2" />
                @can('payments.confirm')
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirm_now" value="1" id="cn" checked><label class="form-check-label" for="cn">Confirmar imediatamente (valor recebido e verificado)</label></div>
                @endcan
                <button class="btn btn-success"><i class="bi bi-check2 me-1"></i>Registar pagamento</button>
                <a href="{{ route('admin.payments.create') }}" class="btn btn-link">Outro empréstimo</a>
            </form>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card"><div class="card-header"><h2 class="card-title">{{ $loan->loan_number }}</h2></div><div class="card-body">
            <dl class="dl-grid small">
                <dt>Cliente</dt><dd>{{ $loan->client->full_name }}</dd>
                <dt>Em dívida</dt><dd class="fw-bold">@money($loan->total_outstanding)</dd>
                <dt>Dias de atraso</dt><dd>{{ $loan->days_past_due }}</dd>
                @if ($next)<dt>Próxima prestação</dt><dd>N.º {{ $next->number }} · @date($next->due_date) · @money($next->outstandingTotal())</dd>@endif
            </dl>
            <p class="small text-muted mb-0">Imputação: prestações mais antigas primeiro; em cada prestação, penalizações → encargos → juros → capital. Para liquidação total utilize a <a href="{{ route('admin.loans.settlement', $loan) }}">liquidação antecipada</a>.</p>
        </div></div>
    </div>
</div>
@endif
@endsection

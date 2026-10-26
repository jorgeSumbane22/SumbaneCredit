@extends('layouts.client')
@section('title', 'Meus empréstimos')

@section('content')
<x-page-header title="Meus empréstimos" :breadcrumbs="['Início' => route('client.dashboard'), 'Empréstimos' => null]" />

<div class="row g-4">
    @forelse ($loans as $loan)
        @php($progress = \App\Support\Money::isPositive($loan->total_contract_amount) ? min(100, round((float) \App\Support\Money::div($loan->total_paid, $loan->total_contract_amount) * 100)) : 0)
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <div><div class="small-label">{{ $loan->product->name }}</div><div class="fw-bold text-navy">{{ $loan->loan_number }}</div></div>
                        <div><x-badge :status="$loan->status" /> @if ($loan->days_past_due > 0)<x-badge label="Em atraso" color="danger" />@endif</div>
                    </div>
                    <dl class="dl-grid small mb-3">
                        <dt>Montante</dt><dd>@money($loan->principal_amount)</dd>
                        <dt>Saldo em dívida</dt><dd>@money($loan->total_outstanding)</dd>
                        <dt>Próximo vencimento</dt><dd>@date($loan->next_due_date)</dd>
                    </dl>
                    <div class="progress mb-1"><div class="progress-bar" style="width: {{ $progress }}%"></div></div>
                    <div class="small text-muted mb-3">{{ $progress }}% pago</div>
                    <a href="{{ route('client.loans.show', $loan) }}" class="btn btn-outline-primary btn-sm w-100 stretched-link">Ver detalhes</a>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12"><div class="card"><x-empty-state icon="briefcase" title="Ainda não tem empréstimos" text="Após a aprovação de um pedido, o empréstimo aparece aqui." /></div></div>
    @endforelse
</div>
<div class="mt-3">{{ $loans->links() }}</div>
@endsection

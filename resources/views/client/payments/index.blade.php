@extends('layouts.client')
@section('title', 'Meus pagamentos')

@section('content')
<x-page-header title="Meus pagamentos" :breadcrumbs="['Início' => route('client.dashboard'), 'Pagamentos' => null]">
    <a href="{{ route('client.payments.create') }}" class="btn btn-success"><i class="bi bi-upload me-1"></i>Enviar comprovativo</a>
</x-page-header>

<form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-sm-6 col-md-4"><x-select name="estado" label="Estado" :options="\App\Enums\PaymentStatus::options()" :value="$status" placeholder="Todos" col="mb-0" /></div>
    <div class="col-auto"><button class="btn btn-primary" data-no-loading>Filtrar</button></div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Data</th><th>Empréstimo</th><th>Método</th><th>Referência</th><th class="num">Valor</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            @forelse ($payments as $p)
                <tr>
                    <td>@date($p->paid_at)</td>
                    <td>{{ $p->loan->loan_number }}</td>
                    <td>{{ $p->method->label() }}</td>
                    <td class="small">{{ $p->reference ?? '—' }}</td>
                    <td class="num fw-semibold">@money($p->amount)</td>
                    <td><x-badge :status="$p->status" /></td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('client.payments.show', $p) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                        @if ($p->receipt_number)<a href="{{ route('client.payments.receipt', $p) }}" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-receipt"></i></a>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty-state icon="cash-stack" title="Sem pagamentos registados" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $payments->links() }}</div>
@endsection

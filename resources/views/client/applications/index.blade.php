@extends('layouts.client')
@section('title', 'Meus pedidos')

@section('content')
<x-page-header title="Meus pedidos de crédito" :breadcrumbs="['Início' => route('client.dashboard'), 'Pedidos' => null]">
    <a href="{{ route('client.applications.create') }}" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i>Novo pedido</a>
</x-page-header>

<form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-sm-6 col-md-4"><x-select name="estado" label="Estado" :options="\App\Enums\ApplicationStatus::options()" :value="$status" placeholder="Todos" col="mb-0" /></div>
    <div class="col-auto"><button class="btn btn-primary" data-no-loading>Filtrar</button></div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>N.º</th><th>Produto</th><th class="num">Montante</th><th>Prestações</th><th>Submetido</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            @forelse ($applications as $a)
                <tr>
                    <td class="fw-semibold">{{ $a->application_number }}</td>
                    <td>{{ $a->product->name }}</td>
                    <td class="num">@money($a->approved_amount ?? $a->requested_amount)</td>
                    <td>{{ $a->approved_term ?? $a->term }} · {{ $a->repayment_frequency->label() }}</td>
                    <td class="small">@datetime($a->submitted_at)</td>
                    <td><x-badge :status="$a->status" /></td>
                    <td class="text-end"><a href="{{ route('client.applications.show', $a) }}" class="btn btn-sm btn-outline-primary">Ver</a></td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty-state icon="file-earmark-text" title="Sem pedidos" text="Submeta o seu primeiro pedido de crédito."><a href="{{ route('client.applications.create') }}" class="btn btn-sm btn-success">Pedir crédito</a></x-empty-state></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $applications->links() }}</div>
@endsection

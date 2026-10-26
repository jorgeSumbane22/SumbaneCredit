@extends('layouts.client')
@section('title', 'Minhas prestações')

@section('content')
<x-page-header title="Minhas prestações" :breadcrumbs="['Início' => route('client.dashboard'), 'Prestações' => null]" />

<form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-sm-6 col-md-3"><x-select name="filtro" label="Mostrar" :options="['em-aberto' => 'Em aberto', 'em-atraso' => 'Em atraso', 'pagas' => 'Pagas', 'todas' => 'Todas']" :value="$filter" col="mb-0" /></div>
    <div class="col-sm-6 col-md-3"><x-select name="emprestimo" label="Empréstimo" :options="$loans->pluck('loan_number', 'id')->all()" :value="request('emprestimo')" placeholder="Todos" col="mb-0" /></div>
    <div class="col-auto"><button class="btn btn-primary" data-no-loading>Filtrar</button></div>
</form>

<div class="card">
    @include('loans.partials.installments-table', ['installments' => $installments, 'showLoan' => true])
</div>
<div class="mt-3">{{ $installments->links() }}</div>
@endsection

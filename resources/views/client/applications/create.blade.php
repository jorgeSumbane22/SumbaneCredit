@extends('layouts.client')
@section('title', 'Novo pedido de crédito')

@section('content')
<x-page-header title="Novo pedido de crédito" subtitle="Simule, preencha os dados e submeta. Será notificado em cada etapa da análise." :breadcrumbs="['Início' => route('client.dashboard'), 'Pedidos' => route('client.applications.index'), 'Novo' => null]" />

@if ($openApplication)
    <div class="alert alert-warning">
        <i class="bi bi-hourglass-split me-1"></i>Já tem o pedido <a href="{{ route('client.applications.show', $openApplication) }}" class="fw-semibold">{{ $openApplication->application_number }}</a> em análise. Só é possível ter um pedido em aberto de cada vez.
    </div>
@elseif ($products->isEmpty())
    <x-empty-state icon="box" title="Não há produtos disponíveis neste momento" />
@else
    @include('loans.partials.application-form')
@endif
@endsection

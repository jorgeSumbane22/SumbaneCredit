@extends('layouts.admin')
@section('title', 'Novo pedido — '.$client->full_name)

@section('content')
<x-page-header :title="'Novo pedido para '.$client->full_name" subtitle="Registo ao balcão. As declarações e consentimentos devem ser prestados pelo cliente." :breadcrumbs="['Clientes' => route('admin.clients.index'), $client->client_number => route('admin.clients.show', $client), 'Novo pedido' => null]" />
@if ($products->isEmpty())
    <x-empty-state icon="box" title="Não existem produtos activos" />
@else
    @include('loans.partials.application-form')
@endif
@endsection

@extends('layouts.admin')
@section('title', $client ? 'Editar cliente' : 'Novo cliente')

@section('content')
<x-page-header :title="$client ? 'Editar '.$client->full_name : 'Registar cliente'" :breadcrumbs="['Clientes' => route('admin.clients.index'), ($client ? $client->client_number : 'Novo') => null]" />

<div class="card"><div class="card-body">
    <form method="POST" action="{{ $client ? route('admin.clients.update', $client) : route('admin.clients.store') }}">
        @csrf
        @if ($client) @method('PUT') @endif
        @include('clients.partials.form-fields', ['client' => $client, 'lockIdentity' => false])
        <x-textarea name="notes" label="Observações internas" :value="$client?->notes" rows="2" />
        @unless ($client)
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="create_account" id="create_account" value="1" @checked(old('create_account'))>
                <label class="form-check-label" for="create_account">Criar acesso ao portal do cliente (é enviado um email para o cliente definir a palavra-passe; requer email)</label>
            </div>
        @endunless
        <button class="btn btn-success" type="submit"><i class="bi bi-check2 me-1"></i>Guardar</button>
        <a href="{{ $client ? route('admin.clients.show', $client) : route('admin.clients.index') }}" class="btn btn-link">Cancelar</a>
    </form>
</div></div>
@endsection

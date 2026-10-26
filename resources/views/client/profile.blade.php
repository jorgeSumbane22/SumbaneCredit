@extends('layouts.client')
@section('title', 'Perfil')

@section('content')
<x-page-header title="O meu perfil" subtitle="Mantenha os seus dados actualizados. São utilizados na análise de crédito." :breadcrumbs="['Início' => route('client.dashboard'), 'Perfil' => null]" />

<div class="row g-4">
    <div class="col-lg-9">
        <div class="card"><div class="card-body">
            <form method="POST" action="{{ route('client.profile.update') }}">
                @csrf @method('PUT')
                @include('clients.partials.form-fields', ['client' => $client, 'lockIdentity' => $client->kyc_status === 'verified'])
                <button class="btn btn-success" type="submit"><i class="bi bi-check2 me-1"></i>Guardar alterações</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-3">
        <div class="card mb-3"><div class="card-body">
            <div class="small-label">N.º de cliente</div>
            <div class="fw-bold text-navy mb-2">{{ $client->client_number }}</div>
            <div class="small-label">Identificação</div>
            <x-badge :label="\App\Models\Client::KYC_STATUSES[$client->kyc_status]" :color="['pending' => 'warning', 'verified' => 'success', 'rejected' => 'danger'][$client->kyc_status]" />
        </div></div>
        <div class="card"><div class="card-body small text-muted">
            <i class="bi bi-shield-lock text-success me-1"></i>Os seus dados pessoais são tratados de forma confidencial e apenas para a gestão da relação de crédito. Consulte a <a href="{{ route('public.privacy') }}">política de privacidade</a>.
        </div></div>
    </div>
</div>
@endsection

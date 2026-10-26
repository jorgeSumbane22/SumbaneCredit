@extends('layouts.auth')
@section('title', 'Criar conta')

@section('content')
    <h1 class="h3 fw-bold text-navy mb-1">Criar conta de cliente</h1>
    <p class="text-muted mb-4">Registe-se para simular, pedir e acompanhar o seu crédito.</p>

    <form method="POST" action="{{ route('register') }}" novalidate>
        @csrf
        <x-input name="full_name" label="Nome completo" required autocomplete="name" />
        <div class="row">
            <x-input name="email" type="email" label="Email" required col="col-md-6 mb-3" autocomplete="email" />
            <x-input name="phone" label="Telemóvel" required col="col-md-6 mb-3" placeholder="84 123 4567" inputmode="tel" />
        </div>
        <div class="row">
            <x-select name="document_type" label="Documento" :options="\App\Models\Client::DOCUMENT_TYPES" value="bi" required col="col-md-5 mb-3" />
            <x-input name="document_number" label="N.º do documento" required col="col-md-7 mb-3" />
        </div>
        <div class="row">
            <x-input name="birth_date" type="date" label="Data de nascimento" required col="col-md-6 mb-3" max="{{ now()->subYears(18)->toDateString() }}" />
            <x-select name="province" label="Província" :options="array_combine(\App\Models\Client::PROVINCES, \App\Models\Client::PROVINCES)" placeholder="Seleccione" required col="col-md-6 mb-3" />
        </div>
        <div class="row">
            <x-input name="password" type="password" label="Palavra-passe" required col="col-md-6 mb-3" autocomplete="new-password" />
            <x-input name="password_confirmation" type="password" label="Confirmar" required col="col-md-6 mb-3" autocomplete="new-password" />
        </div>
        <div class="form-text mb-3 mt-n2">Mínimo 8 caracteres, com maiúsculas, minúsculas e números.</div>
        <div class="form-check mb-2">
            <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" name="terms" id="terms" value="1" @checked(old('terms'))>
            <label class="form-check-label small" for="terms">Li e aceito os <a href="{{ route('public.terms') }}" target="_blank">termos e condições</a>.</label>
        </div>
        <div class="form-check mb-4">
            <input class="form-check-input @error('privacy') is-invalid @enderror" type="checkbox" name="privacy" id="privacy" value="1" @checked(old('privacy'))>
            <label class="form-check-label small" for="privacy">Autorizo o tratamento dos meus dados pessoais nos termos da <a href="{{ route('public.privacy') }}" target="_blank">política de privacidade</a>.</label>
        </div>
        <button class="btn btn-success w-100 py-2" type="submit"><i class="bi bi-person-plus me-1"></i>Criar conta</button>
    </form>
    <p class="text-center small text-muted mt-4 mb-0">Já tem conta? <a href="{{ route('login') }}" class="fw-semibold">Inicie sessão</a></p>
@endsection

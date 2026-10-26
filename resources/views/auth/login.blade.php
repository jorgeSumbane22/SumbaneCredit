@extends('layouts.auth')
@section('title', 'Entrar')

@section('content')
    <h1 class="h3 fw-bold text-navy mb-1">Bem-vindo(a) de volta</h1>
    <p class="text-muted mb-4">Inicie sessão para aceder à sua área.</p>

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf
        <x-input name="email" type="email" label="Email" required autofocus autocomplete="username" />
        <x-input name="password" type="password" label="Palavra-passe" required autocomplete="current-password" />
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                <label class="form-check-label small" for="remember">Manter sessão iniciada</label>
            </div>
            <a href="{{ route('password.request') }}" class="small">Esqueceu a palavra-passe?</a>
        </div>
        <button class="btn btn-primary w-100 py-2" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i>Entrar</button>
    </form>

    <p class="text-center small text-muted mt-4 mb-0">Ainda não tem conta? <a href="{{ route('register') }}" class="fw-semibold">Registe-se</a></p>
@endsection

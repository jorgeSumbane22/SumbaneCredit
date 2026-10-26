@extends('layouts.auth')
@section('title', 'Recuperar palavra-passe')

@section('content')
    <h1 class="h3 fw-bold text-navy mb-1">Recuperar palavra-passe</h1>
    <p class="text-muted mb-4">Indique o seu email. Enviaremos uma ligação para definir uma nova palavra-passe.</p>
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <x-input name="email" type="email" label="Email" required autofocus />
        <button class="btn btn-primary w-100 py-2" type="submit"><i class="bi bi-envelope me-1"></i>Enviar ligação</button>
    </form>
    <p class="text-center small mt-4 mb-0"><a href="{{ route('login') }}"><i class="bi bi-arrow-left me-1"></i>Voltar ao início de sessão</a></p>
@endsection

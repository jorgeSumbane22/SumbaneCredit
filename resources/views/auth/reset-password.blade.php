@extends('layouts.auth')
@section('title', 'Nova palavra-passe')

@section('content')
    <h1 class="h3 fw-bold text-navy mb-1">Definir nova palavra-passe</h1>
    <p class="text-muted mb-4">Escolha uma palavra-passe forte que não utilize noutros serviços.</p>
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-input name="email" type="email" label="Email" :value="$email" required />
        <x-input name="password" type="password" label="Nova palavra-passe" required autocomplete="new-password" help="Mínimo 8 caracteres, com maiúsculas, minúsculas e números." />
        <x-input name="password_confirmation" type="password" label="Confirmar palavra-passe" required autocomplete="new-password" />
        <button class="btn btn-primary w-100 py-2" type="submit">Guardar palavra-passe</button>
    </form>
@endsection

@extends('layouts.auth')
@section('title', 'Verificação em dois passos')

@section('content')
    <h1 class="h4 fw-bold text-navy mb-1">Verificação em dois passos</h1>
    <p class="text-muted mb-4">Introduza o código de 6 dígitos gerado pela sua aplicação autenticadora.</p>
    <form method="POST" action="{{ route('two-factor.challenge') }}">
        @csrf
        <x-input name="code" label="Código de autenticação" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus />
        <details class="mb-3">
            <summary class="small text-muted">Usar um código de recuperação</summary>
            <div class="mt-2"><x-input name="recovery_code" label="Código de recuperação" placeholder="XXXXXXXX-XXXXXXXX" /></div>
        </details>
        <button class="btn btn-primary w-100" type="submit">Verificar</button>
    </form>
    <p class="text-center small mt-4 mb-0"><a href="{{ route('login') }}">Voltar</a></p>
@endsection

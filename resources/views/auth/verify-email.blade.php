@extends('layouts.auth')
@section('title', 'Confirmar email')

@section('content')
    <div class="text-center mb-4"><i class="bi bi-envelope-check text-success" style="font-size:3rem"></i></div>
    <h1 class="h4 fw-bold text-navy text-center mb-2">Confirme o seu email</h1>
    <p class="text-muted text-center mb-4">Enviámos uma ligação de confirmação para <strong>{{ auth()->user()->email }}</strong>. Abra o email e clique na ligação para activar o acesso ao portal.</p>
    <form method="POST" action="{{ route('verification.send') }}" class="mb-3">
        @csrf
        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-arrow-repeat me-1"></i>Reenviar email de confirmação</button>
    </form>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="btn btn-link w-100 text-muted" type="submit">Terminar sessão</button>
    </form>
    @if (app()->isLocal() && config('mail.default') === 'log')
        <div class="alert alert-info small mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Ambiente local: os emails são escritos em <code>storage/logs/laravel.log</code> (MAIL_MAILER=log), não são enviados.</div>
    @endif
@endsection

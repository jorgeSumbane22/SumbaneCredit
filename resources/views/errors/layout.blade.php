@extends('layouts.base')
@section('title', $title)
@section('body')
<div class="min-vh-100 d-flex align-items-center justify-content-center p-3">
    <div class="text-center" style="max-width:520px">
        <a href="{{ url('/') }}"><img src="{{ asset('images/logo.svg') }}" alt="SumbaneCredit" height="44" class="mb-4"></a>
        <div class="display-3 fw-bold text-navy">{{ $code }}</div>
        <h1 class="h4 fw-bold mb-2">{{ $title }}</h1>
        <p class="text-muted mb-4">{{ $text }}</p>
        <div class="d-flex justify-content-center gap-2">
            <a href="javascript:history.back()" class="btn btn-outline-primary">Voltar</a>
            <a href="{{ auth()->check() ? route('home') : url('/') }}" class="btn btn-success">{{ auth()->check() ? 'A minha área' : 'Página inicial' }}</a>
        </div>
    </div>
</div>
@endsection

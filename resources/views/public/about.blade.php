@extends('layouts.public')
@section('title', 'Sobre nós')

@section('content')
@include('public.partials.page-hero', ['title' => 'Sobre nós', 'subtitle' => $site['tagline']])
<section class="section">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-7">
                <h2 class="section-title mb-3">Apresentação</h2>
                <div class="prose">
                    @if ($about)
                        {!! $about !!}
                    @else
                        <p class="text-muted">A apresentação institucional ainda não foi configurada no painel administrativo.</p>
                    @endif
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card mb-3"><div class="card-body">
                    <h5 class="fw-bold text-navy"><i class="bi bi-bullseye text-success me-2"></i>Missão</h5>
                    <p class="text-muted mb-0">{{ $mission ?: 'A definir pela instituição.' }}</p>
                </div></div>
                <div class="card mb-3"><div class="card-body">
                    <h5 class="fw-bold text-navy"><i class="bi bi-eye text-success me-2"></i>Visão</h5>
                    <p class="text-muted mb-0">{{ $vision ?: 'A definir pela instituição.' }}</p>
                </div></div>
                <div class="card"><div class="card-body">
                    <h5 class="fw-bold text-navy"><i class="bi bi-gem text-success me-2"></i>Valores</h5>
                    @if ($values)
                        <ul class="mb-0 text-muted">@foreach ($values as $value)<li>{{ $value }}</li>@endforeach</ul>
                    @else
                        <p class="text-muted mb-0">A definir pela instituição.</p>
                    @endif
                </div></div>
            </div>
        </div>
        <div class="card mt-5"><div class="card-body">
            <h5 class="fw-bold text-navy">Informação institucional</h5>
            <dl class="dl-grid">
                <dt>Denominação</dt><dd>{{ $site['legal_name'] ?: $site['name'] }}</dd>
                <dt>Autorização / registo</dt><dd>{{ $site['registration_info'] ?: 'Informação a publicar pela instituição.' }}</dd>
                <dt>Endereço</dt><dd>{{ $site['address'] ?: '—' }}</dd>
                <dt>Contactos</dt><dd>{{ collect([$site['phone'], $site['email']])->filter()->implode(' · ') ?: '—' }}</dd>
            </dl>
        </div></div>
    </div>
</section>
@endsection

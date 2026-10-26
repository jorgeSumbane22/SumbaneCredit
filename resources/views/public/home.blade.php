@extends('layouts.public')
@section('title', 'Microcrédito simples e transparente')

@section('content')
<section class="hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="pill mb-3"><i class="bi bi-lightning-charge-fill text-warning"></i>Pedido 100% online · Acompanhamento em tempo real</span>
                <h1 class="mb-3">{{ $heroTitle }}</h1>
                <p class="lead mb-4">{{ $heroSubtitle }}</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('register') }}" class="btn btn-success btn-lg"><i class="bi bi-person-plus me-1"></i>Pedir crédito</a>
                    <a href="#simulador" class="btn btn-outline-light btn-lg"><i class="bi bi-calculator me-1"></i>Simular agora</a>
                </div>
                <div class="d-flex flex-wrap gap-4 mt-4 small" style="color:#c9d5e6">
                    <span><i class="bi bi-check2-circle text-success me-1"></i>Condições claras antes de pedir</span>
                    <span><i class="bi bi-check2-circle text-success me-1"></i>Pagamentos por M-Pesa, e-Mola ou banco</span>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="hero-card p-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="stat-icon icon-green"><i class="bi bi-calculator"></i></span>
                        <div><div class="fw-bold text-navy">Simulação rápida</div><div class="small text-muted">Resultado indicativo, sem compromisso</div></div>
                    </div>
                    @if ($products->isNotEmpty())
                        @include('public.partials.simulator', ['url' => route('public.simulate'), 'compact' => true])
                    @else
                        <x-empty-state icon="box" title="Produtos em preparação" text="Os produtos de crédito serão publicados brevemente." />
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section bg-white">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="eyebrow mb-2">Quem somos</div>
                <h2 class="section-title mb-3">Microcrédito pensado para quem trabalha e empreende em Moçambique</h2>
                <div class="text-muted prose">
                    @if ($about)
                        {!! \Illuminate\Support\Str::limit(strip_tags($about, '<p><strong><em>'), 600) !!}
                    @else
                        <p>O {{ $site['name'] }} apoia pessoas e pequenos negócios com crédito rápido, condições transparentes e acompanhamento próximo — do pedido à liquidação.</p>
                    @endif
                </div>
                <a href="{{ route('public.about') }}" class="btn btn-outline-primary mt-2">Conhecer a instituição</a>
            </div>
            <div class="col-lg-6">
                <div class="row g-3">
                    @foreach ([
                        ['shield-check', 'Transparência', 'Taxas, encargos e calendário apresentados antes da submissão.'],
                        ['phone', 'Tudo no telemóvel', 'Pedido, documentos, prestações e recibos no portal.'],
                        ['clock-history', 'Resposta rápida', 'Análise célere e notificações em cada etapa.'],
                        ['people', 'Acompanhamento', 'Equipa disponível para ajudar em caso de dificuldade.'],
                    ] as [$icon, $title, $text])
                        <div class="col-sm-6">
                            <div class="feature">
                                <span class="feature-icon"><i class="bi bi-{{ $icon }}"></i></span>
                                <h5 class="fw-bold text-navy">{{ $title }}</h5>
                                <p class="text-muted small mb-0">{{ $text }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="text-center mb-5">
            <div class="eyebrow mb-2">Produtos de crédito</div>
            <h2 class="section-title">Escolha a solução adequada</h2>
            <p class="section-subtitle mx-auto">Condições configuradas pela instituição e apresentadas com clareza.</p>
        </div>
        <div class="row g-4">
            @forelse ($products as $product)
                <div class="col-md-6 col-lg-4">@include('public.partials.product-card', ['product' => $product])</div>
            @empty
                <div class="col-12"><x-empty-state icon="box" title="Ainda não há produtos publicados" /></div>
            @endforelse
        </div>
    </div>
</section>

<section class="section bg-white">
    <div class="container">
        <div class="text-center mb-5">
            <div class="eyebrow mb-2">Processo de solicitação</div>
            <h2 class="section-title">Do pedido ao desembolso em 4 passos</h2>
        </div>
        <div class="row g-4">
            @foreach ([
                ['Crie a sua conta', 'Registe-se e confirme o seu email.'],
                ['Simule e peça', 'Escolha o produto, veja a simulação e submeta o pedido com os documentos.'],
                ['Análise e decisão', 'A equipa analisa o pedido; é notificado de cada etapa.'],
                ['Contrato e desembolso', 'Após a formalização do contrato, os fundos são transferidos.'],
            ] as $i => [$title, $text])
                <div class="col-sm-6 col-lg-3">
                    <div class="step">
                        <span class="step-num">{{ $i + 1 }}</span>
                        <h5 class="fw-bold text-navy">{{ $title }}</h5>
                        <p class="text-muted small">{{ $text }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="section" id="simulador">
    <div class="container">
        <div class="text-center mb-5">
            <div class="eyebrow mb-2">Simulador</div>
            <h2 class="section-title">Simule a sua prestação</h2>
            <p class="section-subtitle mx-auto">Utiliza o mesmo motor de cálculo aplicado aos contratos. A simulação não constitui aprovação de crédito.</p>
        </div>
        @if ($products->isNotEmpty())
            @include('public.partials.simulator', ['url' => route('public.simulate')])
        @endif
    </div>
</section>

@if ($testimonials->isNotEmpty())
<section class="section bg-white">
    <div class="container">
        <div class="text-center mb-5">
            <div class="eyebrow mb-2">Testemunhos</div>
            <h2 class="section-title">O que dizem os clientes</h2>
        </div>
        <div class="row g-4">
            @foreach ($testimonials as $t)
                <div class="col-md-4">
                    <div class="feature">
                        @if ($t->is_demo)<span class="demo-badge mb-2 d-inline-block"><i class="bi bi-info-circle me-1"></i>Exemplo de demonstração</span>@endif
                        <p class="fst-italic text-muted">“{{ $t->content }}”</p>
                        <div class="fw-semibold text-navy">{{ $t->name }}</div>
                        <div class="small text-muted">{{ $t->role }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@if ($faqs->isNotEmpty())
<section class="section">
    <div class="container" style="max-width:860px">
        <div class="text-center mb-4">
            <div class="eyebrow mb-2">Perguntas frequentes</div>
            <h2 class="section-title">Tire as suas dúvidas</h2>
        </div>
        @include('public.partials.faq-list', ['faqs' => $faqs])
        <div class="text-center mt-3"><a href="{{ route('public.faq') }}">Ver todas as perguntas</a></div>
    </div>
</section>
@endif

<section class="pb-5">
    <div class="container">
        <div class="cta-band d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h3 class="fw-bold mb-1">Pronto para avançar?</h3>
                <p class="mb-0 opacity-75">Crie a sua conta e submeta o pedido em poucos minutos.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('register') }}" class="btn btn-light btn-lg text-navy fw-semibold">Criar conta</a>
                <a href="{{ route('public.contact') }}" class="btn btn-outline-light btn-lg">Contactar-nos</a>
            </div>
        </div>
    </div>
</section>
@endsection

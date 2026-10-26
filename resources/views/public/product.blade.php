@extends('layouts.public')
@section('title', $product->name)

@section('content')
@include('public.partials.page-hero', ['title' => $product->name, 'subtitle' => $version->rateLabel()])
<section class="section">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card mb-4"><div class="card-body">
                    <h2 class="h5 fw-bold text-navy">Descrição</h2>
                    <p class="text-muted">{!! nl2br(e($product->description)) !!}</p>
                    <h2 class="h5 fw-bold text-navy mt-4">Condições financeiras</h2>
                    <dl class="dl-grid">
                        <dt>Montante</dt><dd>@money($version->min_amount) a @money($version->max_amount)</dd>
                        <dt>Prazo</dt><dd>{{ $version->min_term }} a {{ $version->max_term }} prestações</dd>
                        <dt>Periodicidade</dt><dd>{{ collect($version->frequencies())->map->label()->implode(', ') }}</dd>
                        <dt>Taxa de juro</dt><dd>{{ $version->rateLabel() }} (taxa nominal; conversão proporcional por período)</dd>
                        <dt>Método de cálculo</dt><dd>{{ collect($version->methods())->map->label()->implode('; ') }}</dd>
                        <dt>Encargos</dt><dd>{{ $version->feesLabel() }}</dd>
                        <dt>Atrasos</dt><dd>{{ $version->penaltyLabel() }}</dd>
                        <dt>Liquidação antecipada</dt><dd>{{ $version->earlySettlementLabel() }}</dd>
                    </dl>
                    @if ($product->requirements)
                        <h2 class="h5 fw-bold text-navy mt-4">Requisitos</h2>
                        <p class="text-muted">{!! nl2br(e($product->requirements)) !!}</p>
                    @endif
                    @if ($product->contract_conditions)
                        <h2 class="h5 fw-bold text-navy mt-4">Condições contratuais</h2>
                        <p class="text-muted small">{!! nl2br(e($product->contract_conditions)) !!}</p>
                    @endif
                </div></div>
            </div>
            <div class="col-lg-5">
                <div class="card"><div class="card-header"><h2 class="card-title">Simular este produto</h2></div>
                    <div class="card-body">
                        @include('public.partials.simulator', ['products' => collect([$product]), 'url' => route('public.simulate'), 'compact' => true])
                    </div>
                </div>
                <a href="{{ auth()->check() ? route('client.applications.create', ['produto' => $product->id]) : route('register') }}" class="btn btn-success btn-lg w-100 mt-3"><i class="bi bi-send me-1"></i>Solicitar este crédito</a>
            </div>
        </div>
    </div>
</section>
@endsection

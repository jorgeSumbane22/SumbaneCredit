@extends('layouts.public')
@section('title', 'Produtos de crédito')

@section('content')
@include('public.partials.page-hero', ['title' => 'Produtos de crédito', 'subtitle' => 'Montantes, prazos, taxas e encargos de cada produto.'])
<section class="section">
    <div class="container">
        <div class="row g-4">
            @forelse ($products as $product)
                <div class="col-md-6 col-lg-4">@include('public.partials.product-card', ['product' => $product])</div>
            @empty
                <div class="col-12"><x-empty-state icon="box" title="Sem produtos publicados" text="Volte em breve." /></div>
            @endforelse
        </div>
        <p class="small text-muted mt-4 mb-0"><i class="bi bi-info-circle me-1"></i>As condições apresentadas são as que vigoram à data. Alterações futuras não afectam contratos já celebrados. A concessão de crédito depende de análise e aprovação.</p>
    </div>
</section>
@endsection

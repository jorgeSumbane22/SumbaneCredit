@extends('layouts.public')
@section('title', 'Simulador de crédito')

@section('content')
@include('public.partials.page-hero', ['title' => 'Simulador de crédito', 'subtitle' => 'Prestação, juros, encargos e calendário de amortização.'])
<section class="section">
    <div class="container">
        @if ($products->isNotEmpty())
            @include('public.partials.simulator', ['url' => route('public.simulate'), 'selected' => $selected])
        @else
            <x-empty-state icon="box" title="Sem produtos disponíveis para simulação" />
        @endif
        <div class="alert alert-light border small mt-4 mb-0">
            <strong>Como calculamos:</strong> a taxa nominal do produto é convertida proporcionalmente para o período de pagamento (mensal, quinzenal ou semanal).
            Os valores são arredondados ao cêntimo e a última prestação acerta as diferenças de arredondamento. A simulação não constitui aprovação de crédito nem proposta contratual.
        </div>
    </div>
</section>
@endsection

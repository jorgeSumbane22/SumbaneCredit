@extends('layouts.public')
@section('title', 'Perguntas frequentes')

@section('content')
@include('public.partials.page-hero', ['title' => 'Perguntas frequentes'])
<section class="section">
    <div class="container" style="max-width:860px">
        @if ($faqs->isNotEmpty())
            @include('public.partials.faq-list', ['faqs' => $faqs])
        @else
            <x-empty-state icon="question-circle" title="Ainda sem perguntas publicadas" />
        @endif
        <p class="text-center text-muted mt-4">Não encontrou a resposta? <a href="{{ route('public.contact') }}">Contacte-nos</a>.</p>
    </div>
</section>
@endsection

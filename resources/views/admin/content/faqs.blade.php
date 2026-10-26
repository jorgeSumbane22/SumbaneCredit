@extends('layouts.admin')
@section('title', 'Perguntas frequentes')

@section('content')
<x-page-header title="Perguntas frequentes" subtitle="Publicadas no website por ordem crescente." />
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card"><div class="card-header"><h2 class="card-title">Nova pergunta</h2></div><div class="card-body">
            <form method="POST" action="{{ route('admin.faqs.store') }}">@csrf
                <x-input name="question" label="Pergunta" required />
                <x-textarea name="answer" label="Resposta" rows="4" required />
                <x-input name="sort_order" type="number" label="Ordem" value="0" />
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="fa" checked><label class="form-check-label" for="fa">Publicada</label></div>
                <button class="btn btn-success w-100">Adicionar</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        @forelse ($faqs as $faq)
            <div class="card mb-3"><div class="card-body">
                <form method="POST" action="{{ route('admin.faqs.update', $faq) }}">@csrf @method('PUT')
                    <div class="row g-2">
                        <div class="col-md-9"><input class="form-control fw-semibold" name="question" value="{{ $faq->question }}" required></div>
                        <div class="col-md-3"><input class="form-control" type="number" name="sort_order" value="{{ $faq->sort_order }}"></div>
                        <div class="col-12"><textarea class="form-control" name="answer" rows="3" required>{{ $faq->answer }}</textarea></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="fa{{ $faq->id }}" @checked($faq->is_active)><label class="form-check-label small" for="fa{{ $faq->id }}">Publicada</label></div>
                        <button class="btn btn-sm btn-primary">Guardar</button>
                    </div>
                </form>
                <x-action-form :action="route('admin.faqs.destroy', $faq)" method="DELETE" confirm="Remover esta pergunta?" button="Remover" class="btn btn-sm btn-link text-danger p-0" />
            </div></div>
        @empty
            <div class="card"><x-empty-state icon="question-circle" title="Sem perguntas" /></div>
        @endforelse
    </div>
</div>
@endsection

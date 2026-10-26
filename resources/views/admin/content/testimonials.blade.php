@extends('layouts.admin')
@section('title', 'Testemunhos')

@section('content')
<x-page-header title="Testemunhos" subtitle="Testemunhos reais exigem consentimento do autor. Sem consentimento, são apresentados como demonstração." />
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card"><div class="card-header"><h2 class="card-title">Novo testemunho</h2></div><div class="card-body">
            <form method="POST" action="{{ route('admin.testimonials.store') }}">@csrf
                <x-input name="name" label="Nome" required />
                <x-input name="role" label="Actividade / localidade" />
                <x-textarea name="content" label="Testemunho" rows="4" required />
                <div class="form-check"><input class="form-check-input" type="checkbox" name="consent" value="1" id="tc"><label class="form-check-label small" for="tc">Testemunho real com consentimento escrito do autor</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="is_demo" value="1" id="td"><label class="form-check-label small" for="td">Marcar como demonstração</label></div>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ta" checked><label class="form-check-label small" for="ta">Publicado</label></div>
                <button class="btn btn-success w-100">Guardar</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        @forelse ($testimonials as $t)
            <div class="card mb-3"><div class="card-body">
                <form method="POST" action="{{ route('admin.testimonials.update', $t) }}">@csrf @method('PUT')
                    <div class="row g-2">
                        <div class="col-md-6"><input class="form-control" name="name" value="{{ $t->name }}" required></div>
                        <div class="col-md-6"><input class="form-control" name="role" value="{{ $t->role }}"></div>
                        <div class="col-12"><textarea class="form-control" name="content" rows="2" required>{{ $t->content }}</textarea></div>
                    </div>
                    <div class="d-flex flex-wrap gap-3 align-items-center mt-2">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="consent" value="1" id="c{{ $t->id }}" @checked(! $t->is_demo)><label class="form-check-label small" for="c{{ $t->id }}">Real (com consentimento)</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_demo" value="1" id="d{{ $t->id }}" @checked($t->is_demo)><label class="form-check-label small" for="d{{ $t->id }}">Demonstração</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="a{{ $t->id }}" @checked($t->is_active)><label class="form-check-label small" for="a{{ $t->id }}">Publicado</label></div>
                        <button class="btn btn-sm btn-primary ms-auto">Guardar</button>
                    </div>
                </form>
                <x-action-form :action="route('admin.testimonials.destroy', $t)" method="DELETE" confirm="Remover este testemunho?" button="Remover" class="btn btn-sm btn-link text-danger p-0" />
            </div></div>
        @empty
            <div class="card"><x-empty-state icon="chat-quote" title="Sem testemunhos" /></div>
        @endforelse
    </div>
</div>
@endsection

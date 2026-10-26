@extends('layouts.client')
@section('title', 'Novo pedido de suporte')

@section('content')
<x-page-header title="Novo pedido de suporte" :breadcrumbs="['Início' => route('client.dashboard'), 'Suporte' => route('client.tickets.index'), 'Novo' => null]" />
<div class="card" style="max-width:820px"><div class="card-body">
    <form method="POST" action="{{ route('client.tickets.store') }}" enctype="multipart/form-data">
        @csrf
        <x-input name="subject" label="Assunto" required maxlength="150" />
        <div class="row">
            <x-select name="category" label="Categoria" :options="\App\Models\SupportTicket::CATEGORIES" required col="col-md-6 mb-3" />
            <x-select name="priority" label="Prioridade" :options="['low' => 'Baixa', 'normal' => 'Normal', 'high' => 'Alta']" value="normal" required col="col-md-6 mb-3" />
        </div>
        <x-textarea name="message" label="Mensagem" rows="6" required />
        <x-input name="attachment" type="file" label="Anexo (opcional)" accept=".pdf,.jpg,.jpeg,.png,.webp" />
        <button class="btn btn-success" type="submit"><i class="bi bi-send me-1"></i>Enviar</button>
    </form>
</div></div>
@endsection

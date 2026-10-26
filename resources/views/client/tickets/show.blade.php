@extends('layouts.client')
@section('title', 'Ticket '.$ticket->ticket_number)

@section('content')
<x-page-header :title="$ticket->subject" :subtitle="$ticket->ticket_number.' · '.$ticket->categoryLabel()" :breadcrumbs="['Início' => route('client.dashboard'), 'Suporte' => route('client.tickets.index'), $ticket->ticket_number => null]">
    <x-badge :label="$ticket->statusLabel()" :color="$ticket->statusColor()" class="fs-6" />
</x-page-header>
<div class="row"><div class="col-lg-9">
    @include('tickets.partials.conversation', ['attachmentRoute' => 'client.tickets.attachment'])
    @can('reply', $ticket)
        <div class="card mt-3"><div class="card-body">
            <form method="POST" action="{{ route('client.tickets.reply', $ticket) }}" enctype="multipart/form-data">
                @csrf
                <x-textarea name="message" label="Responder" rows="3" required />
                <div class="d-flex flex-wrap gap-2 align-items-end">
                    <x-input name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" col="mb-0" />
                    <button class="btn btn-success" type="submit"><i class="bi bi-send me-1"></i>Enviar</button>
                </div>
            </form>
        </div></div>
    @else
        <div class="alert alert-secondary mt-3">Este pedido está fechado. Abra um novo pedido se necessitar de ajuda.</div>
    @endcan
</div></div>
@endsection

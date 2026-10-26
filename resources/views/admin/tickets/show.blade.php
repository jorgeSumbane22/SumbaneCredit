@extends('layouts.admin')
@section('title', 'Ticket '.$ticket->ticket_number)

@section('content')
<x-page-header :title="$ticket->subject" :subtitle="$ticket->ticket_number.' · '.$ticket->categoryLabel().' · '.$ticket->client->full_name" :breadcrumbs="['Suporte' => route('admin.tickets.index'), $ticket->ticket_number => null]">
    <x-badge :label="$ticket->statusLabel()" :color="$ticket->statusColor()" class="fs-6" />
    <a href="{{ route('admin.clients.show', $ticket->client) }}" class="btn btn-outline-primary">Ficha do cliente</a>
</x-page-header>
<div class="row g-4">
    <div class="col-lg-8">
        @include('tickets.partials.conversation', ['attachmentRoute' => 'admin.tickets.attachment'])
        @if ($ticket->status !== 'closed')
            <div class="card mt-3"><div class="card-body">
                <form method="POST" action="{{ route('admin.tickets.reply', $ticket) }}" enctype="multipart/form-data">@csrf
                    <x-textarea name="message" label="Resposta ao cliente" rows="4" required />
                    <div class="row align-items-end">
                        <x-input name="attachment" type="file" label="Anexo" accept=".pdf,.jpg,.jpeg,.png,.webp" col="col-md-5 mb-3" />
                        <x-select name="status" label="Estado após resposta" :options="\App\Models\SupportTicket::STATUSES" value="awaiting_client" col="col-md-4 mb-3" />
                        <div class="col-md-3 mb-3"><button class="btn btn-success w-100">Responder</button></div>
                    </div>
                </form>
            </div></div>
        @endif
    </div>
    <div class="col-lg-4">
        <div class="card"><div class="card-header"><h2 class="card-title">Gestão</h2></div><div class="card-body">
            <form method="POST" action="{{ route('admin.tickets.status', $ticket) }}">@csrf
                <x-select name="status" label="Estado" :options="\App\Models\SupportTicket::STATUSES" :value="$ticket->status" />
                <x-select name="assigned_to" label="Responsável" :options="$agents->pluck('name', 'id')->all()" :value="$ticket->assigned_to" placeholder="— Sem responsável —" />
                <button class="btn btn-primary w-100">Actualizar</button>
            </form>
            <dl class="dl-grid small mt-3">
                <dt>Prioridade</dt><dd>{{ $ticket->priorityLabel() }}</dd>
                <dt>Aberto</dt><dd>@datetime($ticket->created_at)</dd>
                <dt>Fechado</dt><dd>@datetime($ticket->closed_at)</dd>
            </dl>
        </div></div>
    </div>
</div>
@endsection

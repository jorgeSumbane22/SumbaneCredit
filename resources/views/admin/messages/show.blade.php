@extends('layouts.admin')
@section('title', $message->subject)

@section('content')
<x-page-header :title="$message->subject" :subtitle="$message->name.' · '.$message->email" :breadcrumbs="['Mensagens' => route('admin.messages.index'), '#'.$message->id => null]">
    <a href="mailto:{{ $message->email }}?subject=RE: {{ rawurlencode($message->subject) }}" class="btn btn-outline-primary"><i class="bi bi-reply me-1"></i>Responder por email</a>
</x-page-header>
<div class="row g-4">
    <div class="col-lg-8"><div class="card"><div class="card-body">
        <div class="small text-muted mb-2">@datetime($message->created_at) · {{ $message->phone }} · IP {{ $message->ip_address }}</div>
        <p class="mb-0">{!! nl2br(e($message->message)) !!}</p>
    </div></div></div>
    <div class="col-lg-4"><div class="card"><div class="card-body">
        <form method="POST" action="{{ route('admin.messages.update', $message) }}">@csrf
            <x-select name="status" label="Estado" :options="\App\Models\ContactMessage::STATUSES" :value="$message->status" />
            <x-textarea name="internal_notes" label="Notas internas" :value="$message->internal_notes" rows="4" />
            <button class="btn btn-primary w-100">Guardar</button>
        </form>
        @if ($message->handler)<div class="small text-muted mt-2">Tratada por {{ $message->handler->name }} em @datetime($message->handled_at)</div>@endif
    </div></div></div>
</div>
@endsection

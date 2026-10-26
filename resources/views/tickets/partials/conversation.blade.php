{{-- Conversa de um ticket. Parâmetros: $ticket, $attachmentRoute --}}
@foreach ($ticket->replies as $reply)
    <div class="d-flex mb-3 {{ $reply->is_staff ? '' : 'flex-row-reverse' }}">
        <span class="avatar {{ $reply->is_staff ? 'bg-navy' : '' }} mx-2">{{ $reply->user?->initials() ?? '?' }}</span>
        <div class="card {{ $reply->is_staff ? '' : 'bg-green-soft' }}" style="max-width:78%">
            <div class="card-body py-2 px-3">
                <div class="small fw-semibold">{{ $reply->user?->name ?? 'Utilizador' }} @if ($reply->is_staff)<span class="badge badge-soft-primary">Equipa</span>@endif</div>
                <div class="my-1">{!! nl2br(e($reply->message)) !!}</div>
                @if ($reply->attachment_path)
                    <a href="{{ route($attachmentRoute, $reply) }}" class="small"><i class="bi bi-paperclip"></i> {{ $reply->attachment_name }}</a>
                @endif
                <div class="small text-muted">@datetime($reply->created_at)</div>
            </div>
        </div>
    </div>
@endforeach

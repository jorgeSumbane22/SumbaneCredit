@extends($layout)
@section('title', 'Notificações')

@section('content')
<x-page-header title="Notificações" subtitle="Avisos sobre pedidos, contratos, pagamentos e prestações.">
    <div class="btn-group">
        <a href="{{ route('notifications.index') }}" class="btn btn-sm {{ $filter ? 'btn-outline-primary' : 'btn-primary' }}">Todas</a>
        <a href="{{ route('notifications.index', ['filtro' => 'nao-lidas']) }}" class="btn btn-sm {{ $filter ? 'btn-primary' : 'btn-outline-primary' }}">Não lidas</a>
    </div>
    <form method="POST" action="{{ route('notifications.read-all') }}">
        @csrf
        <button class="btn btn-sm btn-outline-success"><i class="bi bi-check2-all me-1"></i>Marcar todas como lidas</button>
    </form>
</x-page-header>

<div class="card">
    <div class="list-group list-group-flush">
        @forelse ($notifications as $n)
            <a href="{{ route('notifications.open', $n->id) }}" class="list-group-item list-group-item-action py-3 {{ $n->read_at ? '' : 'bg-green-soft' }}">
                <div class="d-flex gap-3">
                    <i class="bi bi-{{ $n->data['icon'] ?? 'bell' }} fs-4 text-{{ $n->data['level'] ?? 'info' }}"></i>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <strong class="text-navy">{{ $n->data['title'] ?? 'Notificação' }}</strong>
                            <small class="text-muted">{{ $n->created_at->format('d/m/Y H:i') }} · {{ $n->created_at->diffForHumans() }}</small>
                        </div>
                        <div class="small text-body">{{ $n->data['message'] ?? '' }}</div>
                    </div>
                    @unless ($n->read_at)<span class="badge badge-soft-success align-self-start">Nova</span>@endunless
                </div>
            </a>
        @empty
            <x-empty-state icon="bell-slash" title="Sem notificações" text="Quando houver novidades sobre os seus pedidos e pagamentos, aparecem aqui." />
        @endforelse
    </div>
</div>
<div class="mt-3">{{ $notifications->links() }}</div>
@endsection

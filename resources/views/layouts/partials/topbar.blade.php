@php($user = auth()->user())
<header class="topbar">
    <button class="icon-btn d-lg-none" type="button" data-toggle-sidebar aria-label="Abrir menu"><i class="bi bi-list fs-4"></i></button>
    <div class="flex-grow-1">
        @isset($search)
            {{ $search }}
        @endisset
    </div>

    <div class="dropdown">
        <button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notificações">
            <i class="bi bi-bell fs-5"></i>
            @if (($unreadCount ?? 0) > 0)
                <span class="count">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
            @endif
        </button>
        <div class="dropdown-menu dropdown-menu-end p-0 notif-menu shadow">
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                <strong class="text-navy">Notificações</strong>
                @if (($unreadCount ?? 0) > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}" data-no-loading>
                        @csrf
                        <button class="btn btn-link btn-sm p-0">Marcar como lidas</button>
                    </form>
                @endif
            </div>
            @forelse ($unreadNotifications ?? [] as $n)
                <a href="{{ route('notifications.open', $n->id) }}" class="dropdown-item notif-item unread">
                    <div class="d-flex gap-2">
                        <i class="bi bi-{{ $n->data['icon'] ?? 'bell' }} text-{{ $n->data['level'] ?? 'info' }} mt-1"></i>
                        <div>
                            <div class="fw-semibold small">{{ $n->data['title'] ?? 'Notificação' }}</div>
                            <div class="small text-muted">{{ \Illuminate\Support\Str::limit($n->data['message'] ?? '', 110) }}</div>
                            <div class="small text-muted">{{ $n->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                </a>
            @empty
                <div class="text-center text-muted small py-4"><i class="bi bi-check2-circle d-block fs-3 mb-1"></i>Sem notificações por ler</div>
            @endforelse
            <a href="{{ route('notifications.index') }}" class="dropdown-item text-center small py-2">Ver todas</a>
        </div>
    </div>

    <div class="dropdown">
        <button class="btn d-flex align-items-center gap-2 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="avatar">{{ $user->initials() }}</span>
            <span class="d-none d-md-block text-start lh-sm">
                <span class="d-block fw-semibold small text-navy">{{ \Illuminate\Support\Str::limit($user->name, 24) }}</span>
                <span class="d-block small text-muted">{{ $user->roleLabel() }}</span>
            </span>
            <i class="bi bi-chevron-down small text-muted"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow">
            @if ($user->isClient())
                <li><a class="dropdown-item" href="{{ route('client.profile.edit') }}"><i class="bi bi-person me-2"></i>O meu perfil</a></li>
            @endif
            <li><a class="dropdown-item" href="{{ route('account.security') }}"><i class="bi bi-shield-lock me-2"></i>Segurança da conta</a></li>
            <li><a class="dropdown-item" href="{{ route('notifications.index') }}"><i class="bi bi-bell me-2"></i>Notificações</a></li>
            <li><a class="dropdown-item" href="{{ route('public.home') }}" target="_blank"><i class="bi bi-globe me-2"></i>Website</a></li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Terminar sessão</button>
                </form>
            </li>
        </ul>
    </div>
</header>

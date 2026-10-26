@extends($layout)
@section('title', 'Segurança da conta')

@section('content')
<x-page-header title="Segurança da conta" subtitle="Palavra-passe, sessões activas e autenticação de dois factores." />

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header"><h2 class="card-title">Alterar palavra-passe</h2></div>
            <div class="card-body">
                <form method="POST" action="{{ route('account.password') }}">
                    @csrf @method('PUT')
                    <x-input name="current_password" type="password" label="Palavra-passe actual" required autocomplete="current-password" />
                    <x-input name="password" type="password" label="Nova palavra-passe" required autocomplete="new-password" help="Mínimo 8 caracteres, com maiúsculas, minúsculas e números." />
                    <x-input name="password_confirmation" type="password" label="Confirmar nova palavra-passe" required autocomplete="new-password" />
                    <button class="btn btn-primary" type="submit"><i class="bi bi-key me-1"></i>Alterar palavra-passe</button>
                </form>
                @if ($user->password_changed_at)
                    <p class="small text-muted mt-3 mb-0">Última alteração: @datetime($user->password_changed_at)</p>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Autenticação de dois factores (2FA)</h2></div>
            <div class="card-body">
                @if ($user->hasTwoFactorEnabled())
                    <div class="alert alert-success"><i class="bi bi-shield-check me-1"></i>Activa desde @datetime($user->two_factor_confirmed_at).</div>
                    @if ($recoveryCodes)
                        <div class="alert alert-warning">
                            <div class="fw-semibold mb-2">Códigos de recuperação (guarde-os em local seguro — só são mostrados agora):</div>
                            <div class="row row-cols-2 g-1 font-monospace small">
                                @foreach ($recoveryCodes as $code)<div class="col">{{ $code }}</div>@endforeach
                            </div>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('account.2fa.disable') }}" class="row g-2 align-items-end">
                        @csrf @method('DELETE')
                        <div class="col-sm-8"><x-input name="password" type="password" label="Confirme com a palavra-passe" required col="mb-0" id="pw_disable" /></div>
                        <div class="col-sm-4"><button class="btn btn-outline-danger w-100" type="submit">Desactivar</button></div>
                    </form>
                @elseif ($pendingSecret)
                    <p class="small">1. Na sua aplicação autenticadora, adicione uma conta com a chave abaixo (ou abra a ligação no telemóvel):</p>
                    <div class="bg-light rounded p-3 font-monospace text-break mb-2">{{ chunk_split($pendingSecret, 4, ' ') }}</div>
                    <a href="{{ $otpauthUrl }}" class="small d-inline-block mb-3"><i class="bi bi-phone me-1"></i>Abrir na aplicação autenticadora</a>
                    <p class="small">2. Introduza o código de 6 dígitos apresentado:</p>
                    <form method="POST" action="{{ route('account.2fa.confirm') }}" class="row g-2">
                        @csrf
                        <div class="col-sm-8"><x-input name="code" inputmode="numeric" maxlength="6" placeholder="000000" required col="mb-0" /></div>
                        <div class="col-sm-4"><button class="btn btn-success w-100" type="submit">Confirmar</button></div>
                    </form>
                @else
                    <p class="text-muted small">Proteja a sua conta exigindo, além da palavra-passe, um código gerado no seu telemóvel (aplicações TOTP como Google Authenticator ou Microsoft Authenticator).</p>
                    <form method="POST" action="{{ route('account.2fa.start') }}">
                        @csrf
                        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-shield-plus me-1"></i>Activar 2FA</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Sessões activas</h2></div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Dispositivo</th><th>IP</th><th>Última actividade</th></tr></thead>
                    <tbody>
                    @forelse ($sessions as $s)
                        <tr>
                            <td class="small">{{ $s->agent ?: 'Desconhecido' }} @if ($s->current)<span class="badge badge-soft-success ms-1">Esta sessão</span>@endif</td>
                            <td class="small">{{ $s->ip }}</td>
                            <td class="small">{{ $s->last_activity->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><x-empty-state icon="laptop" title="Sem sessões registadas" /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body border-top">
                <form method="POST" action="{{ route('account.sessions.destroy') }}" class="row g-2 align-items-end">
                    @csrf @method('DELETE')
                    <div class="col-sm-8"><x-input name="password" type="password" label="Terminar as outras sessões (confirme a palavra-passe)" required col="mb-0" id="pw_sessions" /></div>
                    <div class="col-sm-4"><button class="btn btn-outline-danger w-100" type="submit">Terminar</button></div>
                </form>
            </div>
        </div>
        <div class="card mt-4">
            <div class="card-body small text-muted">
                <div><strong>Último acesso:</strong> @datetime($user->last_login_at) @if ($user->last_login_ip) (IP {{ $user->last_login_ip }}) @endif</div>
                <div><strong>Email:</strong> {{ $user->email }} @if ($user->email_verified_at)<span class="badge badge-soft-success">verificado</span>@endif</div>
            </div>
        </div>
    </div>
</div>
@endsection

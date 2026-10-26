@extends('layouts.admin')
@section('title', $user->name)

@section('content')
<x-page-header :title="$user->name" :subtitle="$user->roleLabel().' · '.$user->email" :breadcrumbs="['Funcionários' => route('admin.staff.index'), $user->name => null]">
    <x-badge :label="$user->isActive() ? 'Activo' : 'Inactivo'" :color="$user->isActive() ? 'success' : 'secondary'" class="fs-6" />
    <a href="{{ route('admin.staff.edit', $user) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
    @if ($sessions->isNotEmpty())
        <x-action-form :action="route('admin.staff.sessions', $user)" method="DELETE" confirm="Terminar todas as sessões deste utilizador?" button="Terminar sessões" icon="box-arrow-right" class="btn btn-outline-danger" confirm-color="#DC3545" />
    @endif
</x-page-header>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-body">
            <dl class="dl-grid small">
                <dt>Telefone</dt><dd>{{ $user->phone ?? '—' }}</dd>
                <dt>2FA</dt><dd>{{ $user->hasTwoFactorEnabled() ? 'Activa' : 'Não activa' }}</dd>
                <dt>Último acesso</dt><dd>@datetime($user->last_login_at)</dd>
                <dt>IP</dt><dd>{{ $user->last_login_ip ?? '—' }}</dd>
                <dt>Registado em</dt><dd>@datetime($user->created_at)</dd>
            </dl>
        </div></div>
        <div class="card"><div class="card-header"><h2 class="card-title">Sessões activas ({{ $sessions->count() }})</h2></div>
            <ul class="list-group list-group-flush small">
                @forelse ($sessions as $s)
                    <li class="list-group-item">{{ $s->ip_address }} · {{ \Carbon\Carbon::createFromTimestamp($s->last_activity)->diffForHumans() }}<div class="text-muted">{{ \Illuminate\Support\Str::limit($s->user_agent, 70) }}</div></li>
                @empty
                    <li class="list-group-item text-muted">Sem sessões activas.</li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card"><div class="card-header"><h2 class="card-title">Actividade registada</h2></div>
            <div class="table-responsive"><table class="table table-sm table-hover">
                <thead><tr><th>Data</th><th>Acção</th><th>Registo</th><th>Resultado</th></tr></thead>
                <tbody>
                @forelse ($activity as $a)
                    <tr><td class="small text-nowrap">@datetime($a->created_at)</td><td class="small"><a href="{{ auth()->user()->can('audit.view') ? route('admin.audit.show', $a) : '#' }}">{{ $a->action }}</a><div class="text-muted">{{ $a->description }}</div></td>
                        <td class="small">{{ $a->subjectLabel() }}</td><td><x-badge :label="$a->result === 'success' ? 'Sucesso' : 'Falha'" :color="$a->result === 'success' ? 'success' : 'danger'" /></td></tr>
                @empty
                    <tr><td colspan="4"><x-empty-state icon="activity" title="Sem actividade" /></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="mt-3">{{ $activity->links() }}</div>
    </div>
</div>
@endsection

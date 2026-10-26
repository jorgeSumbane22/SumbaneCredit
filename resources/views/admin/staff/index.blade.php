@extends('layouts.admin')
@section('title', 'Funcionários')

@section('content')
<x-page-header title="Funcionários" subtitle="Contas de acesso ao painel, perfis e estado.">
    <a href="{{ route('admin.staff.create') }}" class="btn btn-success"><i class="bi bi-person-plus me-1"></i>Novo funcionário</a>
</x-page-header>

<form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-md-4"><x-input name="q" label="Pesquisar" :value="request('q')" placeholder="Nome ou email" col="mb-0" /></div>
    <div class="col-sm-6 col-md-3"><x-select name="perfil" label="Perfil" :options="\App\Models\User::STAFF_ROLES" :value="request('perfil')" placeholder="Todos" col="mb-0" /></div>
    <div class="col-sm-6 col-md-2"><x-select name="estado" label="Estado" :options="['active' => 'Activo', 'inactive' => 'Inactivo']" :value="request('estado')" placeholder="Todos" col="mb-0" /></div>
    <div class="col-auto"><button class="btn btn-primary" data-no-loading>Filtrar</button></div>
</form>

<div class="card"><div class="table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Nome</th><th>Email</th><th>Perfil</th><th>2FA</th><th>Último acesso</th><th>Estado</th><th class="text-end">Acções</th></tr></thead>
        <tbody>
        @forelse ($staff as $u)
            <tr>
                <td><div class="d-flex align-items-center gap-2"><span class="avatar">{{ $u->initials() }}</span><a href="{{ route('admin.staff.show', $u) }}" class="fw-semibold">{{ $u->name }}</a></div></td>
                <td class="small">{{ $u->email }}</td>
                <td><span class="badge badge-soft-primary">{{ $u->roleLabel() }}</span></td>
                <td>{!! $u->hasTwoFactorEnabled() ? '<i class="bi bi-shield-check text-success"></i>' : '<i class="bi bi-shield text-muted"></i>' !!}</td>
                <td class="small">{{ $u->last_login_at?->diffForHumans() ?? 'Nunca' }}</td>
                <td><x-badge :label="$u->isActive() ? 'Activo' : 'Inactivo'" :color="$u->isActive() ? 'success' : 'secondary'" /></td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('admin.staff.edit', $u) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    @if ($u->id !== auth()->id())
                        <x-action-form :action="route('admin.staff.toggle', $u)" :confirm="$u->isActive() ? 'Desactivar a conta e terminar as sessões deste funcionário?' : 'Reactivar a conta?'" :button="$u->isActive() ? 'Desactivar' : 'Activar'" :class="$u->isActive() ? 'btn btn-sm btn-outline-danger' : 'btn btn-sm btn-outline-success'" />
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty-state icon="person-badge" title="Sem funcionários" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $staff->links() }}</div>
@endsection

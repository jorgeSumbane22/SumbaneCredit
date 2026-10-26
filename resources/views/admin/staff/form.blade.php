@extends('layouts.admin')
@section('title', $user ? 'Editar funcionário' : 'Novo funcionário')

@section('content')
<x-page-header :title="$user ? 'Editar '.$user->name : 'Novo funcionário'" :breadcrumbs="['Funcionários' => route('admin.staff.index'), ($user ? $user->name : 'Novo') => null]" />
<div class="card" style="max-width:720px"><div class="card-body">
    <form method="POST" action="{{ $user ? route('admin.staff.update', $user) : route('admin.staff.store') }}">
        @csrf
        @if ($user) @method('PUT') @endif
        <x-input name="name" label="Nome completo" :value="$user?->name" required />
        <div class="row">
            <x-input name="email" type="email" label="Email" :value="$user?->email" required col="col-md-7 mb-3" />
            <x-input name="phone" label="Telefone" :value="$user?->phone" col="col-md-5 mb-3" />
        </div>
        <x-select name="role" label="Perfil de acesso" :options="$roles" :value="$user?->getRoleNames()->first()" placeholder="Seleccione" required help="As permissões de cada perfil são geridas em Perfis e permissões." />
        @unless ($user)
            <div class="alert alert-light border small"><i class="bi bi-envelope me-1"></i>Não é definida palavra-passe aqui: o funcionário recebe por email uma ligação para a definir.</div>
        @endunless
        <button class="btn btn-success" type="submit">Guardar</button>
        <a href="{{ route('admin.staff.index') }}" class="btn btn-link">Cancelar</a>
    </form>
</div></div>
@endsection

@extends('layouts.admin')
@section('title', 'Perfis e permissões')

@section('content')
<x-page-header title="Perfis e permissões" subtitle="As permissões são verificadas no servidor em todas as rotas e operações." />

<div class="alert alert-info small"><i class="bi bi-shield-lock me-1"></i>Segregação de funções: o perfil <strong>Super Administrador</strong> gere configuração, utilizadores e permissões, mas não pode receber permissões para aprovar crédito, autorizar/executar desembolsos, confirmar ou estornar pagamentos.</div>

<div class="accordion" id="roles">
    @foreach ($roles as $role)
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#role{{ $role->id }}">
                    <strong class="me-2">{{ \App\Models\User::STAFF_ROLES[$role->name] }}</strong>
                    <span class="badge badge-soft-secondary me-2">{{ $counts[$role->name] }} utilizador(es)</span>
                    <span class="badge badge-soft-primary">{{ $role->permissions->count() }} permissões</span>
                </button>
            </h2>
            <div id="role{{ $role->id }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#roles">
                <div class="accordion-body">
                    <form method="POST" action="{{ route('admin.roles.update', $role) }}" data-confirm="Guardar as permissões do perfil {{ \App\Models\User::STAFF_ROLES[$role->name] }}?">
                        @csrf @method('PUT')
                        <div class="row">
                            @foreach ($permissions as $perm => $label)
                                @php($blocked = $role->name === 'super-admin' && in_array($perm, $forbidden, true))
                                <div class="col-md-6 col-xl-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $perm }}" id="p{{ $role->id }}_{{ $loop->index }}"
                                               @checked($role->permissions->contains('name', $perm)) @disabled($blocked)>
                                        <label class="form-check-label small {{ $blocked ? 'text-muted text-decoration-line-through' : '' }}" for="p{{ $role->id }}_{{ $loop->index }}">{{ $label }} <code class="small">{{ $perm }}</code></label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <button class="btn btn-success mt-3" type="submit">Guardar permissões</button>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection

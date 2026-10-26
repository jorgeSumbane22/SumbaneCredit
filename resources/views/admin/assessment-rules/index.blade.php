@extends('layouts.admin')
@section('title', 'Regras de avaliação')

@section('content')
<x-page-header title="Regras da avaliação de crédito" subtitle="Critérios transparentes de apoio à decisão, versionados e validados pela instituição." />

<div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-1"></i>Estes limiares <strong>não constituem um modelo de risco validado</strong>. São parâmetros de referência que a instituição deve calibrar e validar com base na sua política de crédito e na regulamentação aplicável. A recomendação automática nunca substitui a decisão do gestor autorizado. Não são utilizados atributos sensíveis (género, religião, etnia, estado civil, etc.).</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card"><div class="card-header"><h2 class="card-title">Em vigor: {{ $active ? 'v'.$active->version.' — '.$active->name : 'valores de referência do sistema (v0)' }}</h2></div>
            <div class="card-body">
                @can('settings.manage')
                <form method="POST" action="{{ route('admin.assessment-rules.store') }}">@csrf
                    @foreach ($labels as $key => [$label, $type])
                        <div class="row align-items-center mb-2">
                            <label class="col-8 small" for="r_{{ $key }}">{{ $label }}</label>
                            <div class="col-4"><input type="number" step="{{ $type === 'ratio' ? '0.1' : '1' }}" min="0" class="form-control form-control-sm" id="r_{{ $key }}" name="rules[{{ $key }}]" value="{{ old('rules.'.$key, $current[$key]) }}" required></div>
                        </div>
                    @endforeach
                    <hr>
                    <x-input name="name" label="Nome da versão" required placeholder="Ex.: Política 2026 — revisão 1" />
                    <x-textarea name="notes" label="Fundamentação" rows="2" />
                    <button class="btn btn-success">Criar nova versão (para validação)</button>
                </form>
                @else
                    <dl class="dl-grid small">@foreach ($labels as $key => [$label])<dt>{{ $label }}</dt><dd>{{ $current[$key] }}</dd>@endforeach</dl>
                @endcan
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-header"><h2 class="card-title">Versões</h2></div>
            <div class="table-responsive"><table class="table table-sm">
                <thead><tr><th>Versão</th><th>Criada</th><th>Validada</th><th></th></tr></thead>
                <tbody>
                @forelse ($sets as $s)
                    <tr class="{{ $s->is_active ? 'table-success' : '' }}">
                        <td><strong>v{{ $s->version }}</strong> {{ $s->name }}@if ($s->notes)<div class="small text-muted">{{ $s->notes }}</div>@endif</td>
                        <td class="small">{{ $s->creator?->name }}<br>@datetime($s->created_at)</td>
                        <td class="small">{{ $s->validator?->name ?? '—' }}<br>@datetime($s->validated_at)</td>
                        <td class="text-end">
                            @if ($s->is_active)<span class="badge badge-soft-success">Em vigor</span>
                            @else @can('applications.approve')<x-action-form :action="route('admin.assessment-rules.activate', $s)" confirm="Validar e colocar esta versão em vigor? As novas avaliações passam a usá-la." button="Validar" class="btn btn-sm btn-outline-success" />@endcan @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="small text-muted p-3">Sem versões registadas — são usados os valores de referência.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection

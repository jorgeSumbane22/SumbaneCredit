@extends('layouts.admin')
@section('title', 'Definições')

@section('content')
<x-page-header title="Definições" subtitle="Configuração institucional, conteúdos do website e regras operacionais." />

<div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-1"></i>Os dados institucionais (denominação legal, NUIT, autorização do Banco de Moçambique, contactos) devem ser preenchidos pela instituição com informação verdadeira. O sistema não gera nem presume estes dados.</div>

<form method="POST" action="{{ route('admin.settings.update') }}">
    @csrf @method('PUT')
    <ul class="nav nav-tabs mb-3">
        @foreach ($groups as $g => $label)
            <li class="nav-item"><button class="nav-link {{ $loop->first ? 'active' : '' }}" type="button" data-bs-toggle="tab" data-bs-target="#g-{{ $g }}">{{ $label }}</button></li>
        @endforeach
        <li class="nav-item"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#g-integrations">Integrações</button></li>
    </ul>
    <div class="tab-content">
        @foreach ($groups as $g => $label)
            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="g-{{ $g }}">
                <div class="card"><div class="card-body">
                    @foreach ($definitions[$g] ?? [] as $key => [$group, $type, $fieldLabel])
                        @php($name = 'settings['.str_replace('.', '__', $key).']')
                        @php($value = $values[$key])
                        @if ($type === 'boolean')
                            <div class="form-check form-switch mb-3">
                                <input type="hidden" name="{{ $name }}" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" name="{{ $name }}" value="1" id="s{{ md5($key) }}" @checked(in_array((string) $value, ['1', 'true'], true))>
                                <label class="form-check-label" for="s{{ md5($key) }}">{{ $fieldLabel }}</label>
                            </div>
                        @elseif (in_array($type, ['text', 'html'], true))
                            <div class="mb-3">
                                <label class="form-label">{{ $fieldLabel }}</label>
                                <textarea name="{{ $name }}" rows="{{ $type === 'html' ? 8 : 3 }}" class="form-control {{ $type === 'html' ? 'font-monospace small' : '' }}">{{ $value }}</textarea>
                                @if ($type === 'html')<div class="form-text">HTML simples permitido: &lt;p&gt;, &lt;h2&gt;, &lt;h3&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;ul&gt;, &lt;ol&gt;, &lt;li&gt;, &lt;a&gt;. Scripts e atributos de eventos são removidos.</div>@endif
                            </div>
                        @else
                            <div class="mb-3">
                                <label class="form-label">{{ $fieldLabel }}</label>
                                <input type="{{ $type === 'number' ? 'number' : 'text' }}" name="{{ $name }}" value="{{ $value }}" class="form-control">
                                @if ($key === 'finance.allocation_order')<div class="form-text">Ordem de imputação em cada prestação: penalty (penalizações), fee (encargos), interest (juros), principal (capital).</div>@endif
                            </div>
                        @endif
                    @endforeach
                    <button class="btn btn-success" type="submit">Guardar definições</button>
                </div></div>
            </div>
        @endforeach
        <div class="tab-pane fade" id="g-integrations">
            <div class="card"><div class="card-body">
                <p class="small text-muted">As integrações são configuradas através de variáveis de ambiente (.env). Nenhuma integração externa é simulada.</p>
                <dl class="dl-grid">
                    @foreach ($integrations as $name => $status)<dt>{{ $name }}</dt><dd>{{ $status }}</dd>@endforeach
                </dl>
            </div></div>
        </div>
    </div>
</form>
@endsection

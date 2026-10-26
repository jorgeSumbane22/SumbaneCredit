@extends('layouts.admin')
@section('title', 'Relatórios')

@section('content')
<x-page-header title="Relatórios" subtitle="Relatórios com filtros por período e exportação para PDF e Excel." />
<div class="row g-3">
    @foreach ($types as $key => [$title, $icon, $desc])
        <div class="col-sm-6 col-lg-4 col-xl-3">
            <div class="card h-100">
                <div class="card-body d-flex gap-3">
                    <span class="stat-icon icon-navy"><i class="bi bi-{{ $icon }}"></i></span>
                    <div>
                        <h3 class="h6 fw-bold text-navy mb-1">{{ $title }}</h3>
                        <p class="small text-muted mb-0">{{ $desc }}</p>
                        <a href="{{ route('admin.reports.show', $key) }}" class="stretched-link"></a>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection

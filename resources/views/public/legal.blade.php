@extends('layouts.public')
@section('title', $title)

@section('content')
@include('public.partials.page-hero', ['title' => $title])
<section class="section">
    <div class="container" style="max-width:860px">
        <div class="card"><div class="card-body prose p-4">
            @if ($content)
                {!! $content !!}
            @else
                <p class="text-muted mb-0">Este documento ainda não foi publicado pela instituição.</p>
            @endif
        </div></div>
    </div>
</section>
@endsection

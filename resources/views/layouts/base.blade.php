<!DOCTYPE html>
<html lang="pt-MZ">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $site['name'] }} — Sistema Inteligente de Gestão de Microcrédito. {{ $site['tagline'] }}">
    <title>@hasSection('title')@yield('title') · @endif{{ $site['name'] }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/icon.svg') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
    @stack('styles')
</head>
<body class="@yield('body-class')">
    @yield('body')

    {{-- Mensagens de sessão (apresentadas como toast) --}}
    @foreach (['success', 'error', 'warning', 'info'] as $type)
        @if (session($type))
            <span data-flash="{{ $type }}" data-message="{{ session($type) }}" hidden></span>
        @endif
    @endforeach

    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/sweetalert2/sweetalert2.all.min.js') }}"></script>
    @stack('chart')
    <script src="{{ asset('js/app.js') }}?v=1"></script>
    @stack('scripts')
</body>
</html>

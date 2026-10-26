<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page { margin: 26mm 14mm 18mm 14mm; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 9.5pt; color: #1f2a37; }
        header { position: fixed; top: -20mm; left: 0; right: 0; height: 16mm; border-bottom: 2px solid #159A74; }
        header .brand { font-size: 15pt; font-weight: bold; color: #142D4E; }
        header .brand span { color: #159A74; }
        header .inst { font-size: 7.5pt; color: #6b7280; }
        footer { position: fixed; bottom: -12mm; left: 0; right: 0; height: 9mm; font-size: 7pt; color: #6b7280; border-top: 1px solid #e3e8ef; padding-top: 2mm; }
        footer .page:after { content: counter(page); }
        h1 { font-size: 14pt; color: #142D4E; margin: 0 0 4px; }
        h2 { font-size: 11pt; color: #142D4E; margin: 14px 0 6px; border-bottom: 1px solid #e3e8ef; padding-bottom: 3px; }
        .muted { color: #6b7280; }
        .small { font-size: 8pt; }
        table { width: 100%; border-collapse: collapse; }
        table.grid th { background: #142D4E; color: #fff; font-size: 7.5pt; text-transform: uppercase; padding: 4px 5px; text-align: left; }
        table.grid td { border-bottom: 1px solid #e3e8ef; padding: 4px 5px; font-size: 8.5pt; }
        table.grid tr:nth-child(even) td { background: #f7f9fb; }
        table.grid tfoot td { font-weight: bold; background: #e6ecf4; }
        table.kv td { padding: 3px 4px; vertical-align: top; }
        table.kv td.k { color: #6b7280; width: 38%; }
        .num { text-align: right; white-space: nowrap; }
        .box { border: 1px solid #e3e8ef; border-radius: 4px; padding: 8px 10px; margin-bottom: 8px; }
        .highlight { background: #e3f5ef; border: 1px solid #159A74; }
        .warning { background: #fff7e6; border: 1px solid #F59E0B; padding: 6px 8px; font-size: 8pt; }
        .danger { color: #DC3545; }
        .stamp { display: inline-block; border: 2px solid #DC3545; color: #DC3545; padding: 3px 10px; font-weight: bold; font-size: 12pt; transform: rotate(-4deg); }
        .sign td { padding-top: 34px; text-align: center; font-size: 8pt; }
        .sign .line { border-top: 1px solid #1f2a37; margin: 0 18px; padding-top: 3px; }
        .clause { margin-bottom: 6px; text-align: justify; }
    </style>
</head>
<body>
<header>
    <table>
        <tr>
            <td><div class="brand">Sumbane<span>Credit</span></div><div class="inst">{{ $institution['legal_name'] ?: $institution['name'] }}@if ($institution['nuit']) · NUIT {{ $institution['nuit'] }}@endif</div></td>
            <td class="num inst">{{ $institution['address'] }}<br>{{ collect([$institution['phone'], $institution['email']])->filter()->implode(' · ') }}</td>
        </tr>
    </table>
</header>
<footer>
    <table><tr>
        <td>Documento gerado pelo sistema em {{ $generatedAt->format('d/m/Y H:i') }} (Africa/Maputo). Valores em Meticais (MZN).</td>
        <td class="num">Página <span class="page"></span></td>
    </tr></table>
</footer>
<main>
    @yield('content')
</main>
</body>
</html>

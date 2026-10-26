<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Interpretação dos filtros de período (?periodo=... ou ?de=AAAA-MM-DD&ate=AAAA-MM-DD). */
final class Period
{
    public const PRESETS = [
        'this_month' => 'Este mês',
        'last_month' => 'Mês anterior',
        'last_3_months' => 'Últimos 3 meses',
        'last_12_months' => 'Últimos 12 meses',
        'this_year' => 'Este ano',
        'last_year' => 'Ano anterior',
        'custom' => 'Personalizado',
    ];

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public static function fromRequest(Request $request, string $default = 'this_month'): array
    {
        $today = CarbonImmutable::today();
        $preset = $request->query('periodo', $default);

        if ($request->filled('de') || $request->filled('ate') || $preset === 'custom') {
            try {
                $from = CarbonImmutable::parse($request->query('de', $today->startOfMonth()->toDateString()))->startOfDay();
                $to = CarbonImmutable::parse($request->query('ate', $today->toDateString()))->endOfDay();
            } catch (\Throwable) {
                [$from, $to] = [$today->startOfMonth(), $today->endOfDay()];
            }

            return $from->lte($to) ? [$from, $to] : [$to->startOfDay(), $from->endOfDay()];
        }

        return match ($preset) {
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'last_3_months' => [$today->subMonths(2)->startOfMonth(), $today->endOfDay()],
            'last_12_months' => [$today->subMonths(11)->startOfMonth(), $today->endOfDay()],
            'this_year' => [$today->startOfYear(), $today->endOfDay()],
            'last_year' => [$today->subYear()->startOfYear(), $today->subYear()->endOfYear()],
            default => [$today->startOfMonth(), $today->endOfDay()],
        };
    }

    public static function label(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return 'Período: '.$from->format('d/m/Y').' a '.$to->format('d/m/Y');
    }
}

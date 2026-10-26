<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Numeração sequencial por série e ano (ex.: EMP-2026-000015), sem duplicados
 * mesmo com pedidos concorrentes (bloqueio da linha da sequência).
 */
class NumberGenerator
{
    public const PREFIXES = [
        'client' => 'CLI',
        'application' => 'PED',
        'loan' => 'EMP',
        'contract' => 'CTR',
        'disbursement' => 'DES',
        'payment' => 'PAG',
        'receipt' => 'REC',
        'transaction' => 'MOV',
        'ticket' => 'TKT',
    ];

    public function next(string $series, bool $withYear = true): string
    {
        $year = $withYear ? (int) now()->format('Y') : 0;

        $value = DB::transaction(function () use ($series, $year) {
            DB::table('sequences')->insertOrIgnore(['name' => $series, 'year' => $year, 'value' => 0]);
            $row = DB::table('sequences')->where('name', $series)->where('year', $year)->lockForUpdate()->first();
            $next = $row->value + 1;
            DB::table('sequences')->where('id', $row->id)->update(['value' => $next]);

            return $next;
        });

        $prefix = self::PREFIXES[$series] ?? strtoupper($series);

        return $withYear
            ? sprintf('%s-%d-%06d', $prefix, $year, $value)
            : sprintf('%s-%06d', $prefix, $value);
    }
}

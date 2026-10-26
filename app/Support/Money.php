<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Aritmética monetária decimal baseada em bcmath.
 *
 * Todos os valores circulam como strings numéricas ("1234.56"). Nunca se usa float
 * em cálculos monetários. A escala interna (SCALE) é usada para resultados intermédios
 * e o arredondamento final para 2 casas decimais segue a regra "meio para cima"
 * (ROUND_HALF_UP, afastando de zero).
 */
final class Money
{
    public const SCALE = 12;

    public const CURRENCY = 'MZN';

    public static function of(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        if (is_float($value)) {
            // Floats só são aceites na fronteira (ex.: dados de teste); são convertidos com precisão fixa.
            $value = number_format($value, 6, '.', '');
        }

        $value = trim((string) $value);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException("Valor monetário inválido: [{$value}]");
        }

        return $value;
    }

    public static function add(string|int|float|null ...$values): string
    {
        $total = '0';
        foreach ($values as $value) {
            $total = bcadd($total, self::of($value), self::SCALE);
        }

        return self::normalize($total);
    }

    public static function sub(string|int|float|null $a, string|int|float|null $b): string
    {
        return self::normalize(bcsub(self::of($a), self::of($b), self::SCALE));
    }

    public static function mul(string|int|float|null $a, string|int|float|null $b): string
    {
        return self::normalize(bcmul(self::of($a), self::of($b), self::SCALE));
    }

    public static function div(string|int|float|null $a, string|int|float|null $b): string
    {
        if (self::isZero($b)) {
            throw new InvalidArgumentException('Divisão por zero.');
        }

        return self::normalize(bcdiv(self::of($a), self::of($b), self::SCALE));
    }

    public static function pow(string|int|float $base, int $exponent): string
    {
        return self::normalize(bcpow(self::of($base), (string) $exponent, self::SCALE));
    }

    /**
     * Arredonda para $decimals casas, meio para cima (afastando de zero).
     */
    public static function round(string|int|float|null $value, int $decimals = 2): string
    {
        $value = self::of($value);
        $half = '0.'.str_repeat('0', $decimals).'5';
        $adjusted = str_starts_with($value, '-')
            ? bcsub($value, $half, self::SCALE)
            : bcadd($value, $half, self::SCALE);

        $result = bcadd($adjusted, '0', $decimals);

        return self::isZero($result) ? bcadd('0', '0', $decimals) : $result;
    }

    public static function cmp(string|int|float|null $a, string|int|float|null $b): int
    {
        return bccomp(self::of($a), self::of($b), self::SCALE);
    }

    public static function eq(string|int|float|null $a, string|int|float|null $b): bool
    {
        return self::cmp($a, $b) === 0;
    }

    public static function gt(string|int|float|null $a, string|int|float|null $b): bool
    {
        return self::cmp($a, $b) === 1;
    }

    public static function gte(string|int|float|null $a, string|int|float|null $b): bool
    {
        return self::cmp($a, $b) >= 0;
    }

    public static function lt(string|int|float|null $a, string|int|float|null $b): bool
    {
        return self::cmp($a, $b) === -1;
    }

    public static function lte(string|int|float|null $a, string|int|float|null $b): bool
    {
        return self::cmp($a, $b) <= 0;
    }

    public static function isZero(string|int|float|null $value): bool
    {
        return bccomp(self::of($value), '0', self::SCALE) === 0;
    }

    public static function isPositive(string|int|float|null $value): bool
    {
        return bccomp(self::of($value), '0', self::SCALE) === 1;
    }

    public static function min(string|int|float|null $a, string|int|float|null $b): string
    {
        return self::lte($a, $b) ? self::of($a) : self::of($b);
    }

    public static function max(string|int|float|null $a, string|int|float|null $b): string
    {
        return self::gte($a, $b) ? self::of($a) : self::of($b);
    }

    /** Soma de uma colecção/array de valores ou de um atributo de cada elemento. */
    public static function sum(iterable $items, ?string $attribute = null): string
    {
        $total = '0';
        foreach ($items as $item) {
            $value = $attribute === null ? $item : data_get($item, $attribute);
            $total = bcadd($total, self::of($value), self::SCALE);
        }

        return self::normalize($total);
    }

    /** Valor final com 2 casas decimais (para persistência). */
    public static function toDb(string|int|float|null $value): string
    {
        return self::round($value, 2);
    }

    /** Formato de apresentação: 1.234.567,89 MZN */
    public static function format(string|int|float|null $value, bool $withCurrency = true): string
    {
        $rounded = self::round($value ?? '0', 2);
        $negative = str_starts_with($rounded, '-');
        [$int, $dec] = explode('.', ltrim($rounded, '-'));
        $int = strrev(implode('.', str_split(strrev($int), 3)));
        $formatted = ($negative ? '-' : '').$int.','.$dec;

        return $withCurrency ? $formatted.' '.self::CURRENCY : $formatted;
    }

    /** Converte um valor introduzido pelo utilizador ("1.234,56", "1234.56", "1 234,56") para string decimal. */
    public static function parseInput(string|int|float|null $input): ?string
    {
        if ($input === null || $input === '') {
            return null;
        }
        $value = str_replace([' ', "\u{00A0}", 'MZN', 'MT'], '', trim((string) $input));
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return preg_match('/^-?\d+(\.\d+)?$/', $value) ? $value : null;
    }

    private static function normalize(string $value): string
    {
        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }
        if ($value === '-0' || $value === '') {
            return '0';
        }

        return $value;
    }
}

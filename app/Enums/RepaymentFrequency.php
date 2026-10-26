<?php

namespace App\Enums;

enum RepaymentFrequency: string
{
    case Monthly = 'monthly';
    case Biweekly = 'biweekly';
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mensal',
            self::Biweekly => 'Quinzenal',
            self::Weekly => 'Semanal',
        };
    }

    /** Número de períodos num ano (base temporal da conversão proporcional de taxas). */
    public function periodsPerYear(): int
    {
        return match ($this) {
            self::Monthly => 12,
            self::Biweekly => 26,
            self::Weekly => 52,
        };
    }

    public function unitLabel(int $count = 2): string
    {
        return match ($this) {
            self::Monthly => $count === 1 ? 'mês' : 'meses',
            self::Biweekly => $count === 1 ? 'quinzena' : 'quinzenas',
            self::Weekly => $count === 1 ? 'semana' : 'semanas',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}

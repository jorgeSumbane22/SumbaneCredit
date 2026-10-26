<?php

namespace App\Enums;

enum AmortizationMethod: string
{
    /** Prestações constantes, juros sobre o saldo devedor (sistema francês / Price). */
    case Annuity = 'annuity';

    /** Amortização de capital constante, juros sobre o saldo devedor (SAC). */
    case EqualPrincipal = 'equal_principal';

    /** Juros simples calculados sobre o capital inicial ("flat"). */
    case Flat = 'flat';

    public function label(): string
    {
        return match ($this) {
            self::Annuity => 'Prestações constantes (juros sobre saldo devedor)',
            self::EqualPrincipal => 'Amortização constante (juros sobre saldo devedor)',
            self::Flat => 'Juros simples sobre o capital inicial',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Annuity => 'Prestações constantes',
            self::EqualPrincipal => 'Amortização constante',
            self::Flat => 'Juros simples',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}

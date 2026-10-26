<?php

namespace App\Enums;

enum LoanStatus: string
{
    case AwaitingContract = 'awaiting_contract';
    case Contracted = 'contracted';
    case Active = 'active';
    case Settled = 'settled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingContract => 'Aguarda formalização',
            self::Contracted => 'Contrato formalizado',
            self::Active => 'Activo',
            self::Settled => 'Liquidado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AwaitingContract => 'warning',
            self::Contracted => 'info',
            self::Active => 'success',
            self::Settled => 'secondary',
            self::Cancelled => 'dark',
        };
    }

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::AwaitingContract => [self::Contracted, self::Cancelled],
            self::Contracted => [self::Active, self::Cancelled],
            self::Active => [self::Settled],
            // Um estorno de pagamento pode reabrir um empréstimo liquidado.
            self::Settled => [self::Active],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}

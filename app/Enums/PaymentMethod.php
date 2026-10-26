<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Mpesa = 'mpesa';
    case Emola = 'emola';
    case BankTransfer = 'bank_transfer';
    case BankDeposit = 'bank_deposit';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Mpesa => 'M-Pesa',
            self::Emola => 'e-Mola',
            self::BankTransfer => 'Transferência bancária',
            self::BankDeposit => 'Depósito bancário',
            self::Cash => 'Numerário',
        };
    }

    /** Métodos electrónicos/bancários exigem referência da operação. */
    public function requiresReference(): bool
    {
        return $this !== self::Cash;
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}

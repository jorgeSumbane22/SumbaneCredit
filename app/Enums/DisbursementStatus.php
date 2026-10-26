<?php

namespace App\Enums;

enum DisbursementStatus: string
{
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aguarda autorização',
            self::Authorized => 'Autorizado (por executar)',
            self::Completed => 'Efectuado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Authorized => 'info',
            self::Completed => 'success',
            self::Cancelled => 'dark',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}

<?php

namespace App\Enums;

enum InstallmentStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Paid = 'paid';
    case Restructured = 'restructured';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Por pagar',
            self::Partial => 'Paga parcialmente',
            self::Paid => 'Paga',
            self::Restructured => 'Reestruturada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'secondary',
            self::Partial => 'warning',
            self::Paid => 'success',
            self::Restructured => 'dark',
        };
    }

    public static function openValues(): array
    {
        return [self::Pending->value, self::Partial->value];
    }
}

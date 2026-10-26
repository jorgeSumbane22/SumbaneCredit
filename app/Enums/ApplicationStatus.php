<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case PendingDocuments = 'pending_documents';
    case Forwarded = 'forwarded';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submetido',
            self::UnderReview => 'Em análise',
            self::PendingDocuments => 'Aguarda informação/documentos',
            self::Forwarded => 'Encaminhado para decisão',
            self::Approved => 'Aprovado',
            self::Rejected => 'Rejeitado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted => 'secondary',
            self::UnderReview => 'info',
            self::PendingDocuments => 'warning',
            self::Forwarded => 'primary',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'dark',
        };
    }

    /** Transições permitidas (validadas no backend). */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Submitted => [self::UnderReview, self::PendingDocuments, self::Forwarded, self::Rejected, self::Cancelled],
            self::UnderReview => [self::PendingDocuments, self::Forwarded, self::Approved, self::Rejected, self::Cancelled],
            self::PendingDocuments => [self::UnderReview, self::Rejected, self::Cancelled],
            self::Forwarded => [self::UnderReview, self::PendingDocuments, self::Approved, self::Rejected, self::Cancelled],
            self::Approved, self::Rejected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Approved, self::Rejected, self::Cancelled], true);
    }

    public function clientCanCancel(): bool
    {
        return in_array($this, [self::Submitted, self::UnderReview, self::PendingDocuments], true);
    }

    public static function openValues(): array
    {
        return [self::Submitted->value, self::UnderReview->value, self::PendingDocuments->value, self::Forwarded->value];
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}

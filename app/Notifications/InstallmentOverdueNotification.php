<?php

namespace App\Notifications;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Support\Money;

class InstallmentOverdueNotification extends AppNotification
{
    public function __construct(public Loan $loan, public LoanInstallment $installment)
    {
        parent::__construct();
    }

    public function title(): string
    {
        return 'Prestação em atraso';
    }

    public function message(): string
    {
        return 'A prestação n.º '.$this->installment->number.' do empréstimo '.$this->loan->loan_number.' venceu a '
            .$this->installment->due_date->format('d/m/Y').' e tem '.Money::format($this->installment->outstandingTotal())
            .' por pagar. Regularize a situação ou contacte-nos para encontrar uma solução.';
    }

    public function url(object $notifiable): ?string
    {
        return route('client.installments.index');
    }

    public function icon(): string
    {
        return 'exclamation-triangle';
    }

    public function level(): string
    {
        return 'danger';
    }

    public function smsText(): ?string
    {
        return config('app.name').': prestação do emp. '.$this->loan->loan_number.' em atraso ('.Money::format($this->installment->outstandingTotal()).'). Contacte-nos.';
    }
}

<?php

namespace App\Notifications;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Support\Money;

class InstallmentDueReminderNotification extends AppNotification
{
    public function __construct(public Loan $loan, public LoanInstallment $installment)
    {
        parent::__construct();
    }

    public function title(): string
    {
        return 'Próxima prestação';
    }

    public function message(): string
    {
        return 'A prestação n.º '.$this->installment->number.' do empréstimo '.$this->loan->loan_number.', no valor de '
            .Money::format($this->installment->outstandingTotal()).', vence a '.$this->installment->due_date->format('d/m/Y').'.';
    }

    public function url(object $notifiable): ?string
    {
        return route('client.installments.index');
    }

    public function icon(): string
    {
        return 'calendar-event';
    }

    public function level(): string
    {
        return 'warning';
    }

    public function smsText(): ?string
    {
        return config('app.name').': prestação de '.Money::format($this->installment->outstandingTotal()).' vence a '.$this->installment->due_date->format('d/m/Y').'.';
    }
}

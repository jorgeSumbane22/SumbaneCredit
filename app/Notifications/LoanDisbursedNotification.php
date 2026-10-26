<?php

namespace App\Notifications;

use App\Models\Disbursement;
use App\Models\Loan;
use App\Support\Money;

class LoanDisbursedNotification extends AppNotification
{
    public function __construct(public Loan $loan, public Disbursement $disbursement)
    {
        parent::__construct();
    }

    public function title(): string
    {
        return 'Empréstimo desembolsado';
    }

    public function message(): string
    {
        return 'Foi desembolsado o valor de '.Money::format($this->disbursement->net_amount).' relativo ao empréstimo '.$this->loan->loan_number
            .' ('.$this->disbursement->method->label().'). A primeira prestação vence a '.$this->loan->first_due_date?->format('d/m/Y').'.';
    }

    public function url(object $notifiable): ?string
    {
        return route('client.loans.show', $this->loan);
    }

    public function icon(): string
    {
        return 'cash-coin';
    }

    public function level(): string
    {
        return 'success';
    }

    public function smsText(): ?string
    {
        return config('app.name').': desembolso de '.Money::format($this->disbursement->net_amount).' (emp. '.$this->loan->loan_number.') efectuado.';
    }
}

<?php

namespace App\Notifications;

use App\Models\Loan;
use App\Models\LoanApplication;
use App\Support\Money;

class ApplicationApprovedNotification extends AppNotification
{
    public function __construct(public LoanApplication $application, public Loan $loan)
    {
        parent::__construct();
    }

    public function title(): string
    {
        return 'Pedido de crédito aprovado';
    }

    public function message(): string
    {
        return 'O pedido '.$this->application->application_number.' foi aprovado: '.Money::format($this->application->approved_amount)
            .' em '.$this->application->approved_term.' prestações. Dirija-se à instituição para a formalização do contrato.';
    }

    public function url(object $notifiable): ?string
    {
        return route('client.loans.show', $this->loan);
    }

    public function icon(): string
    {
        return 'check-circle';
    }

    public function level(): string
    {
        return 'success';
    }

    public function smsText(): ?string
    {
        return config('app.name').': o seu pedido '.$this->application->application_number.' foi aprovado. Consulte o portal.';
    }
}

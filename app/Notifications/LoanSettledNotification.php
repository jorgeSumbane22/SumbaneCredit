<?php

namespace App\Notifications;

use App\Models\Loan;

class LoanSettledNotification extends AppNotification
{
    public function __construct(public Loan $loan)
    {
        parent::__construct();
    }

    public function title(): string
    {
        return 'Empréstimo liquidado';
    }

    public function message(): string
    {
        return 'O empréstimo '.$this->loan->loan_number.' encontra-se integralmente liquidado. Pode descarregar o extracto no portal.';
    }

    public function url(object $notifiable): ?string
    {
        return route('client.loans.show', $this->loan);
    }

    public function icon(): string
    {
        return 'trophy';
    }

    public function level(): string
    {
        return 'success';
    }

    public function smsText(): ?string
    {
        return config('app.name').': empréstimo '.$this->loan->loan_number.' liquidado. Obrigado!';
    }
}

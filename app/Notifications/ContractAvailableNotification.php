<?php

namespace App\Notifications;

use App\Models\Loan;

class ContractAvailableNotification extends AppNotification
{
    public function __construct(public Loan $loan)
    {
        parent::__construct();
    }

    public function title(): string
    {
        return 'Contrato disponível para consulta';
    }

    public function message(): string
    {
        return 'A minuta do contrato '.$this->loan->contract_number.' está disponível no portal. Leia atentamente as condições antes da assinatura.';
    }

    public function url(object $notifiable): ?string
    {
        return route('client.loans.show', $this->loan);
    }

    public function icon(): string
    {
        return 'file-earmark-ruled';
    }
}

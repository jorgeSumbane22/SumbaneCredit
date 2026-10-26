<?php

namespace App\Notifications;

use App\Models\LoanApplication;
use App\Support\Money;

/** Para funcionários: novo pedido aguarda análise. */
class NewApplicationNotification extends AppNotification
{
    public function __construct(public LoanApplication $application)
    {
        parent::__construct();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function title(): string
    {
        return 'Novo pedido de crédito';
    }

    public function message(): string
    {
        return "Pedido {$this->application->application_number} de ".Money::format($this->application->requested_amount).' aguarda análise.';
    }

    public function url(object $notifiable): ?string
    {
        return route('admin.applications.show', $this->application);
    }

    public function icon(): string
    {
        return 'inbox';
    }
}

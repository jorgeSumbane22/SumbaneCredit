<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Support\Money;

class PaymentRejectedNotification extends AppNotification
{
    public function __construct(public Payment $payment)
    {
        parent::__construct();
    }

    public function title(): string
    {
        return 'Comprovativo de pagamento não aceite';
    }

    public function message(): string
    {
        return 'O comprovativo de '.Money::format($this->payment->amount).' não foi aceite. Motivo: '.$this->payment->rejection_reason;
    }

    public function url(object $notifiable): ?string
    {
        return route('client.payments.show', $this->payment);
    }

    public function icon(): string
    {
        return 'x-octagon';
    }

    public function level(): string
    {
        return 'danger';
    }
}

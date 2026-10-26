<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Support\Money;

class PaymentReversedNotification extends AppNotification
{
    public function __construct(public Payment $payment)
    {
        parent::__construct();
    }

    public function title(): string
    {
        return 'Pagamento estornado';
    }

    public function message(): string
    {
        return 'O pagamento '.$this->payment->payment_number.' de '.Money::format($this->payment->amount).' foi estornado. Para esclarecimentos, contacte a instituição.';
    }

    public function url(object $notifiable): ?string
    {
        return route('client.payments.show', $this->payment);
    }

    public function icon(): string
    {
        return 'arrow-counterclockwise';
    }

    public function level(): string
    {
        return 'warning';
    }
}

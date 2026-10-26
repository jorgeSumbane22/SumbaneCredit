<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Support\Money;

class PaymentConfirmedNotification extends AppNotification
{
    public function __construct(public Payment $payment)
    {
        parent::__construct();
    }

    public function title(): string
    {
        return 'Pagamento confirmado';
    }

    public function message(): string
    {
        return 'O pagamento de '.Money::format($this->payment->amount).' ('.$this->payment->method->label()
            .') foi confirmado. Recibo n.º '.$this->payment->receipt_number.'.';
    }

    public function url(object $notifiable): ?string
    {
        return route('client.payments.show', $this->payment);
    }

    public function icon(): string
    {
        return 'receipt';
    }

    public function level(): string
    {
        return 'success';
    }

    public function smsText(): ?string
    {
        return config('app.name').': pagamento de '.Money::format($this->payment->amount).' confirmado. Recibo '.$this->payment->receipt_number.'.';
    }
}

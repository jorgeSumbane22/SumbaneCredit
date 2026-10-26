<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Support\Money;

/** Para funcionários: comprovativo submetido pelo cliente aguarda verificação. */
class PaymentProofSubmittedNotification extends AppNotification
{
    public function __construct(public Payment $payment)
    {
        parent::__construct();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function title(): string
    {
        return 'Comprovativo de pagamento por verificar';
    }

    public function message(): string
    {
        return 'Pagamento '.$this->payment->payment_number.' de '.Money::format($this->payment->amount).' submetido pelo cliente aguarda confirmação.';
    }

    public function url(object $notifiable): ?string
    {
        return route('admin.payments.show', $this->payment);
    }

    public function icon(): string
    {
        return 'hourglass-split';
    }
}

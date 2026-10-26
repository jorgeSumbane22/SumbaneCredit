<?php

namespace App\Policies;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;

class PaymentPolicy
{
    use ChecksOwnership;

    public function view(User $user, Payment $payment): bool
    {
        return $this->staffCan($user, 'payments.view') || $this->owns($user, $payment->client_id);
    }

    public function receipt(User $user, Payment $payment): bool
    {
        return $this->view($user, $payment) && in_array($payment->status, [PaymentStatus::Confirmed, PaymentStatus::Reversed], true);
    }
}

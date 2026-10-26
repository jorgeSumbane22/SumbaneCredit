<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;

class LoanPolicy
{
    use ChecksOwnership;

    public function view(User $user, Loan $loan): bool
    {
        return $this->staffCan($user, 'loans.view') || $this->owns($user, $loan->client_id);
    }

    public function pay(User $user, Loan $loan): bool
    {
        return $this->owns($user, $loan->client_id) && $loan->isActive();
    }
}

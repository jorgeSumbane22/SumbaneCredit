<?php

namespace App\Policies;

use App\Models\LoanApplication;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;

class LoanApplicationPolicy
{
    use ChecksOwnership;

    public function view(User $user, LoanApplication $application): bool
    {
        return $this->staffCan($user, 'applications.view') || $this->owns($user, $application->client_id);
    }

    public function cancel(User $user, LoanApplication $application): bool
    {
        return ($this->owns($user, $application->client_id) && $application->status->clientCanCancel())
            || ($this->staffCan($user, 'applications.review') && $application->status->isOpen());
    }

    public function provideInformation(User $user, LoanApplication $application): bool
    {
        return $this->owns($user, $application->client_id) && $application->status->value === 'pending_documents';
    }

    public function review(User $user, LoanApplication $application): bool
    {
        return $this->staffCan($user, 'applications.review') && $application->status->isOpen();
    }

    /** Decisão: permissão própria e o decisor não pode ser o próprio requerente. */
    public function decide(User $user, LoanApplication $application): bool
    {
        return $this->staffCan($user, 'applications.approve')
            && $application->status->isOpen()
            && $application->client?->user_id !== $user->id;
    }
}

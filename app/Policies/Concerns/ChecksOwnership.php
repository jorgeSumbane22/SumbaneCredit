<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksOwnership
{
    /** O utilizador é o cliente titular do registo? */
    protected function owns(User $user, ?int $clientId): bool
    {
        return $clientId !== null
            && ! $user->isStaff()
            && $user->client !== null
            && $user->client->id === $clientId;
    }

    protected function staffCan(User $user, string $permission): bool
    {
        return $user->isStaff() && $user->isActive() && $user->can($permission);
    }
}

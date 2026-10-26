<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;

class ClientPolicy
{
    use ChecksOwnership;

    public function view(User $user, Client $client): bool
    {
        return $this->staffCan($user, 'clients.view') || $this->owns($user, $client->id);
    }

    public function update(User $user, Client $client): bool
    {
        return $this->staffCan($user, 'clients.update') || $this->owns($user, $client->id);
    }
}

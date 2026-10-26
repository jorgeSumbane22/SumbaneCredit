<?php

namespace App\Policies;

use App\Models\ClientDocument;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;

class ClientDocumentPolicy
{
    use ChecksOwnership;

    /** Download/consulta: titular do documento ou funcionário autorizado. */
    public function view(User $user, ClientDocument $document): bool
    {
        return $this->staffCan($user, 'clients.view') || $this->owns($user, $document->client_id);
    }

    public function verify(User $user, ClientDocument $document): bool
    {
        return $this->staffCan($user, 'clients.documents.verify');
    }
}

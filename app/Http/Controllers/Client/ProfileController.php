<?php

namespace App\Http\Controllers\Client;

use App\Http\Requests\ClientProfileRequest;
use App\Services\ClientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends ClientController
{
    public function __construct(private readonly ClientService $clients) {}

    public function edit(): View
    {
        return view('client.profile', ['client' => $this->client()->load('address', 'employment')]);
    }

    public function update(ClientProfileRequest $request): RedirectResponse
    {
        $client = $this->client();
        // Após verificação da identidade, nome/documentos/data de nascimento só são alterados pela instituição.
        $this->clients->update($client, $request->validated(), $client->kyc_status !== 'verified');

        return redirect()->route('client.profile.edit')->with('success', 'Perfil actualizado.');
    }
}

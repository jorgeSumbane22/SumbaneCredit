<?php

namespace App\Services\Payments\Gateways;

use App\Models\Loan;
use Illuminate\Http\Request;

/**
 * Integração com um prestador (M-Pesa / e-Mola). A comunicação concreta (endpoints, cifra
 * das credenciais, formato das mensagens e validação de callbacks) depende da documentação
 * oficial e do contrato com o prestador, pelo que não é implementada sem esses elementos:
 * nenhuma confirmação é simulada. Enquanto não estiver configurada, lança uma excepção clara.
 */
class ProviderGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly array $config,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function isConfigured(): bool
    {
        return (bool) ($this->config['enabled'] ?? false)
            && ! empty($this->config['api_key'])
            && ! empty($this->config['base_url']);
    }

    public function initiate(Loan $loan, string $amount, string $phone): array
    {
        throw GatewayNotConfiguredException::for($this->label);
    }

    public function parseCallback(Request $request): array
    {
        throw GatewayNotConfiguredException::for($this->label);
    }
}

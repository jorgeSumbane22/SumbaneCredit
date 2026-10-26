<?php

namespace App\Services\Payments\Gateways;

use App\Exceptions\BusinessRuleException;

class GatewayNotConfiguredException extends BusinessRuleException
{
    public static function for(string $provider): self
    {
        return new self("A integração com {$provider} não está disponível: requer contrato com o prestador, credenciais e a especificação técnica oficial. Registe o pagamento manualmente.");
    }
}

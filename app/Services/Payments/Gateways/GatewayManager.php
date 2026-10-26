<?php

namespace App\Services\Payments\Gateways;

use App\Enums\PaymentMethod;
use InvalidArgumentException;

class GatewayManager
{
    public function for(PaymentMethod $method): PaymentGateway
    {
        return match ($method) {
            PaymentMethod::Mpesa => new ProviderGateway('mpesa', 'M-Pesa', config('sumbanecredit.payment_gateways.mpesa', [])),
            PaymentMethod::Emola => new ProviderGateway('emola', 'e-Mola', config('sumbanecredit.payment_gateways.emola', [])),
            default => throw new InvalidArgumentException('O método '.$method->label().' é registado manualmente.'),
        };
    }

    /** @return array<string, bool> estado de configuração de cada integração */
    public function status(): array
    {
        return [
            'mpesa' => $this->for(PaymentMethod::Mpesa)->isConfigured(),
            'emola' => $this->for(PaymentMethod::Emola)->isConfigured(),
        ];
    }
}

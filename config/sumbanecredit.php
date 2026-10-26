<?php

return [
    'demo_password' => env('DEMO_PASSWORD', 'Demo@2026!'),

    'sms' => [
        'driver' => env('SMS_DRIVER', 'null'),
        'sender_id' => env('SMS_SENDER_ID', 'SumbaneCrd'),
    ],

    /*
    | Integrações de pagamento. Os parâmetros dependem do contrato com cada prestador
    | (Vodacom M-Pesa, Movitel e-Mola). Sem credenciais válidas, a integração fica
    | desactivada e os pagamentos são registados e confirmados manualmente.
    */
    'payment_gateways' => [
        'mpesa' => [
            'enabled' => env('MPESA_ENABLED', false),
            'api_key' => env('MPESA_API_KEY'),
            'public_key' => env('MPESA_PUBLIC_KEY'),
            'service_provider_code' => env('MPESA_SERVICE_PROVIDER_CODE'),
            'base_url' => env('MPESA_BASE_URL'),
        ],
        'emola' => [
            'enabled' => env('EMOLA_ENABLED', false),
            'api_key' => env('EMOLA_API_KEY'),
            'merchant_code' => env('EMOLA_MERCHANT_CODE'),
            'base_url' => env('EMOLA_BASE_URL'),
        ],
    ],
];

<?php

namespace App\Services\Payments\Gateways;

use App\Models\Loan;
use Illuminate\Http\Request;

/**
 * Contrato para integrações com prestadores de pagamento (M-Pesa, e-Mola, bancos).
 *
 * Fluxo previsto: initiate() pede ao prestador uma cobrança (ex.: USSD push) e devolve a
 * referência; o prestador confirma de forma assíncrona (callback) e parseCallback() valida a
 * assinatura e devolve os dados. A confirmação no sistema continua a passar por
 * PaymentService::register() + confirm(), com a referência única do prestador.
 */
interface PaymentGateway
{
    public function key(): string;

    public function isConfigured(): bool;

    /** @return array{reference: string, status: string, raw: array} */
    public function initiate(Loan $loan, string $amount, string $phone): array;

    /** @return array{reference: string, amount: string, phone: ?string, success: bool, raw: array} */
    public function parseCallback(Request $request): array;
}

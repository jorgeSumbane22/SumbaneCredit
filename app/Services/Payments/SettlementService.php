<?php

namespace App\Services\Payments;

use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\SettlementCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

/**
 * Liquidação antecipada: simulação do montante e registo do pagamento de liquidação.
 * O pagamento é confirmado pelo fluxo normal (PaymentService::confirm), que recalcula o
 * montante à data do pagamento e aplica as dispensas de juros previstas no contrato.
 */
class SettlementService
{
    public function __construct(
        private readonly SettlementCalculator $calculator,
        private readonly PaymentService $payments,
    ) {}

    public function quote(Loan $loan, ?CarbonImmutable $asOf = null): array
    {
        if ($loan->status !== LoanStatus::Active) {
            throw new BusinessRuleException('Só empréstimos activos podem ser liquidados antecipadamente.');
        }
        $asOf ??= CarbonImmutable::today();
        $installments = LoanInstallment::where('loan_id', $loan->id)
            ->where('schedule_version', $loan->schedule_version)
            ->whereIn('status', InstallmentStatus::openValues())
            ->orderBy('due_date')->orderBy('number')->get();

        return $this->calculator->quote($installments, $asOf, $loan->early_settlement_rule) + [
            'as_of' => $asOf->toDateString(),
            'rule' => $loan->early_settlement_rule,
            'rule_label' => $loan->terms_snapshot['early_settlement_label'] ?? $loan->early_settlement_rule,
        ];
    }

    public function register(Loan $loan, ?User $user, array $data, string $source = 'staff', ?UploadedFile $proof = null, ?string $idempotencyKey = null): Payment
    {
        $quote = $this->quote($loan, CarbonImmutable::parse($data['paid_at']));

        return $this->payments->register($loan, $user, [
            'amount' => $quote['total'],
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'paid_at' => $data['paid_at'],
            'notes' => trim('Liquidação antecipada. '.($data['notes'] ?? '')),
            'payer_phone' => $data['payer_phone'] ?? null,
            'type' => 'settlement',
            'meta' => ['quote_at_registration' => array_diff_key($quote, ['waivers' => true])],
        ], $source, $proof, $idempotencyKey);
    }
}

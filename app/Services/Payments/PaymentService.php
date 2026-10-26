<?php

namespace App\Services\Payments;

use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\LoanSettled;
use App\Events\PaymentConfirmed;
use App\Events\PaymentRejected;
use App\Events\PaymentReversed;
use App\Events\PaymentSubmitted;
use App\Exceptions\BusinessRuleException;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Collections\CollectionService;
use App\Services\DocumentStorageService;
use App\Services\Finance\PaymentAllocator;
use App\Services\Finance\SettlementCalculator;
use App\Services\IdempotencyService;
use App\Services\LedgerService;
use App\Services\Loans\LoanBalanceService;
use App\Services\Loans\LoanService;
use App\Services\NumberGenerator;
use App\Services\SettingsService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Pagamentos: registo → confirmação (imputação + razão) → [estorno].
 *
 * Integridade:
 *  - O pagamento pertence sempre ao cliente do empréstimo (client_id é derivado do empréstimo).
 *  - Referências únicas por método (índice único reference_key) — sem pagamentos duplicados.
 *  - Confirmação e estorno correm em transacção com bloqueio do pagamento, do empréstimo e
 *    das prestações (SELECT ... FOR UPDATE), pelo que operações concorrentes são serializadas.
 *  - Um comprovativo enviado pelo cliente fica "pendente" até confirmação por um funcionário.
 *  - Estornos geram movimentos compensatórios; nada é apagado.
 */
class PaymentService
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly PaymentAllocator $allocator,
        private readonly SettlementCalculator $settlement,
        private readonly LedgerService $ledger,
        private readonly LoanBalanceService $balances,
        private readonly LoanService $loans,
        private readonly CollectionService $collections,
        private readonly SettingsService $settings,
        private readonly IdempotencyService $idempotency,
        private readonly DocumentStorageService $storage,
        private readonly AuditService $audit,
    ) {}

    /**
     * @param  array{amount:string, method:string, reference:?string, paid_at:string, notes:?string, payer_phone:?string, type?:string}  $data
     */
    public function register(Loan $loan, ?User $user, array $data, string $source = 'staff', ?UploadedFile $proof = null, ?string $idempotencyKey = null): Payment
    {
        return DB::transaction(function () use ($loan, $user, $data, $source, $proof, $idempotencyKey) {
            if (! $this->idempotency->claim($idempotencyKey, 'payment.register')) {
                throw new BusinessRuleException('Este pagamento já foi submetido (pedido repetido).');
            }

            $loan = Loan::lockForUpdate()->findOrFail($loan->id);
            if ($loan->status !== LoanStatus::Active) {
                throw new BusinessRuleException('Só é possível registar pagamentos em empréstimos activos.');
            }

            $amount = Money::toDb($data['amount']);
            if (! Money::isPositive($amount)) {
                throw new BusinessRuleException('O valor do pagamento deve ser superior a zero.');
            }

            $type = $data['type'] ?? 'regular';
            $pendingTotal = (string) Payment::where('loan_id', $loan->id)->where('status', PaymentStatus::Pending)->sum('amount');
            if ($type === 'regular' && Money::gt(Money::add($amount, $pendingTotal), $loan->total_outstanding)) {
                throw new BusinessRuleException('O valor (somado a pagamentos pendentes) excede o saldo em dívida de '.Money::format($loan->total_outstanding).'. Para liquidar o empréstimo utilize a liquidação antecipada.');
            }

            $method = PaymentMethod::from($data['method']);
            $reference = isset($data['reference']) && trim((string) $data['reference']) !== '' ? strtoupper(trim($data['reference'])) : null;
            if ($method->requiresReference() && ! $reference) {
                throw new BusinessRuleException('A referência da operação é obrigatória para '.$method->label().'.');
            }

            $paidAt = CarbonImmutable::parse($data['paid_at'])->startOfDay();
            if ($paidAt->gt(CarbonImmutable::today())) {
                throw new BusinessRuleException('A data do pagamento não pode ser futura.');
            }
            if ($loan->disbursed_at && $paidAt->lt($loan->disbursed_at)) {
                throw new BusinessRuleException('A data do pagamento não pode ser anterior ao desembolso.');
            }

            try {
                $payment = Payment::create([
                    'payment_number' => $this->numbers->next('payment'),
                    'loan_id' => $loan->id,
                    'client_id' => $loan->client_id,
                    'amount' => $amount,
                    'method' => $method,
                    'reference' => $reference,
                    'reference_key' => $reference ? $method->value.'|'.$reference : null,
                    'payer_phone' => $data['payer_phone'] ?? null,
                    'paid_at' => $paidAt->toDateString(),
                    'type' => $type,
                    'status' => PaymentStatus::Pending,
                    'source' => $source,
                    'proof_path' => $proof ? $this->storage->store($proof, "loans/{$loan->id}/payments")['path'] : null,
                    'notes' => $data['notes'] ?? null,
                    'meta' => $data['meta'] ?? null,
                    'registered_by' => $user?->id,
                ]);
            } catch (QueryException $e) {
                if (($e->errorInfo[1] ?? null) === 1062) {
                    $this->audit->failure('payment.duplicate_reference', $loan, "Referência duplicada: {$method->value}|{$reference}");
                    throw new BusinessRuleException('Já existe um pagamento registado com esta referência ('.$method->label().' '.$reference.').');
                }
                throw $e;
            }

            $this->idempotency->attach($idempotencyKey, 'payment', $payment->id);
            $this->audit->log('payment.registered', $payment, null, [
                'loan' => $loan->loan_number, 'amount' => $amount, 'method' => $method->value, 'reference' => $reference, 'source' => $source,
            ]);

            if ($source === 'client_portal') {
                PaymentSubmitted::dispatch($payment);
            }

            return $payment;
        });
    }

    public function confirm(Payment $payment, User $user): Payment
    {
        return DB::transaction(function () use ($payment, $user) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== PaymentStatus::Pending) {
                throw new BusinessRuleException('Este pagamento já foi processado ('.$payment->status->label().').');
            }

            $loan = Loan::lockForUpdate()->findOrFail($payment->loan_id);
            if ($loan->status !== LoanStatus::Active) {
                throw new BusinessRuleException('O empréstimo não está activo.');
            }
            if ($payment->client_id !== $loan->client_id) {
                throw new BusinessRuleException('O pagamento não pertence ao cliente do empréstimo.');
            }

            $installments = $this->lockOpenInstallments($loan);
            $meta = $payment->meta ?? [];

            if ($payment->type === 'settlement') {
                $quote = $this->settlement->quote($installments, $payment->paid_at, $loan->early_settlement_rule);
                if (! Money::eq($quote['total'], $payment->amount)) {
                    throw new BusinessRuleException('O valor não corresponde ao montante de liquidação à data do pagamento ('.Money::format($quote['total']).'). Gere uma nova simulação de liquidação.');
                }
                $meta['waivers'] = $this->applyInterestWaivers($loan, $installments, $quote['waivers'], $payment);
                $meta['settlement_quote'] = array_diff_key($quote, ['waivers' => true]);
            }

            $outstanding = Money::sum($installments->map(fn ($i) => $i->outstandingTotal()));
            if (Money::gt($payment->amount, $outstanding)) {
                throw new BusinessRuleException('O valor do pagamento excede o saldo em dívida ('.Money::format($outstanding).').');
            }

            $result = $this->allocator->allocate($installments, (string) $payment->amount, $this->settings->allocationOrder());
            if (Money::isPositive($result['remaining'])) {
                throw new BusinessRuleException('Não foi possível imputar a totalidade do pagamento.');
            }

            $totals = ['principal' => '0', 'interest' => '0', 'fee' => '0', 'penalty' => '0'];
            foreach ($result['allocations'] as $alloc) {
                /** @var LoanInstallment $installment */
                $installment = $alloc['installment'];
                $installment->applyAllocation($alloc);
                $installment->save();

                $payment->allocations()->create([
                    'loan_installment_id' => $installment->id,
                    'principal' => $alloc['principal'], 'interest' => $alloc['interest'],
                    'fee' => $alloc['fee'], 'penalty' => $alloc['penalty'], 'total' => $alloc['total'],
                ]);
                foreach ($totals as $c => $v) {
                    $totals[$c] = Money::add($v, $alloc[$c]);
                }
            }

            $this->ledger->postComponents($loan, 'repayment', 'credit', $totals, $payment, 'Pagamento '.$payment->payment_number, $payment->paid_at);

            $payment->fill([
                'status' => PaymentStatus::Confirmed,
                'allocated_amount' => $payment->amount,
                'confirmed_by' => $user->id,
                'confirmed_at' => now(),
                'receipt_number' => $payment->receipt_number ?? $this->numbers->next('receipt'),
                'meta' => $meta ?: null,
            ])->save();

            $this->balances->refresh($loan);
            $settled = false;
            if (Money::isZero($loan->total_outstanding)) {
                $loan->settled_at = now();
                $this->loans->changeStatus($loan, LoanStatus::Settled, $user, $payment->type === 'settlement' ? 'Liquidação antecipada.' : 'Todas as prestações pagas.');
                $settled = true;
            }
            $this->collections->syncLoan($loan);

            $this->audit->log('payment.confirmed', $payment, ['status' => 'pending'], [
                'status' => 'confirmed', 'allocation' => array_map(fn ($v) => Money::toDb($v), $totals), 'receipt' => $payment->receipt_number,
            ]);

            PaymentConfirmed::dispatch($payment);
            if ($settled) {
                LoanSettled::dispatch($loan);
            }

            return $payment;
        });
    }

    public function reject(Payment $payment, User $user, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $user, $reason) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== PaymentStatus::Pending) {
                throw new BusinessRuleException('Só pagamentos pendentes podem ser rejeitados.');
            }
            $payment->update([
                'status' => PaymentStatus::Rejected,
                'rejected_by' => $user->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
                'reference_key' => null, // a referência pode voltar a ser submetida correctamente
            ]);
            $this->audit->log('payment.rejected', $payment, ['status' => 'pending'], ['status' => 'rejected'], $reason);
            PaymentRejected::dispatch($payment);

            return $payment;
        });
    }

    /**
     * Estorno de um pagamento confirmado. Para manter a imputação consistente, os estornos
     * seguem a ordem inversa (LIFO): só o último pagamento confirmado do empréstimo pode ser
     * estornado; para estornar um anterior, estornam-se primeiro os posteriores.
     */
    public function reverse(Payment $payment, User $user, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $user, $reason) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== PaymentStatus::Confirmed) {
                throw new BusinessRuleException('Só pagamentos confirmados podem ser estornados.');
            }
            $loan = Loan::lockForUpdate()->findOrFail($payment->loan_id);

            $later = Payment::where('loan_id', $loan->id)->where('status', PaymentStatus::Confirmed)
                ->where('id', '!=', $payment->id)->where('confirmed_at', '>', $payment->confirmed_at)->exists();
            if ($later) {
                throw new BusinessRuleException('Existem pagamentos confirmados posteriores. Estorne-os primeiro (ordem inversa).');
            }
            if ($payment->allocations()->whereHas('installment', fn ($q) => $q->where('schedule_version', '!=', $loan->schedule_version))->exists()) {
                throw new BusinessRuleException('O pagamento foi imputado a um plano entretanto reestruturado e não pode ser estornado.');
            }

            $totals = ['principal' => '0', 'interest' => '0', 'fee' => '0', 'penalty' => '0'];
            foreach ($payment->allocations()->where('is_reversed', false)->get() as $alloc) {
                $installment = LoanInstallment::lockForUpdate()->findOrFail($alloc->loan_installment_id);
                $installment->paid_at = null;
                $installment->applyAllocation([
                    'principal' => (string) $alloc->principal, 'interest' => (string) $alloc->interest,
                    'fee' => (string) $alloc->fee, 'penalty' => (string) $alloc->penalty,
                ], -1);
                $installment->save();
                $alloc->update(['is_reversed' => true]);
                foreach ($totals as $c => $v) {
                    $totals[$c] = Money::add($v, (string) $alloc->{$c});
                }
            }
            $this->ledger->postComponents($loan, 'payment_reversal', 'debit', $totals, $payment, 'Estorno do pagamento '.$payment->payment_number);

            // Repõe juros dispensados numa liquidação antecipada.
            foreach ($payment->meta['waivers'] ?? [] as $installmentId => $amount) {
                $installment = LoanInstallment::lockForUpdate()->find($installmentId);
                if ($installment) {
                    $installment->interest_waived = Money::toDb(Money::sub($installment->interest_waived, $amount));
                    $installment->paid_at = null;
                    $installment->recalculate();
                    $installment->save();
                    $this->ledger->post($loan, 'payment_reversal', 'interest', 'debit', $amount, $payment, 'Reposição de juros dispensados (estorno da liquidação)');
                }
            }

            PaymentReversal::create([
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'reason' => $reason,
                'reversed_by' => $user->id,
                'reversed_at' => now(),
            ]);
            $payment->update(['status' => PaymentStatus::Reversed]);

            $this->balances->refresh($loan);
            if ($loan->status === LoanStatus::Settled && Money::isPositive($loan->total_outstanding)) {
                $loan->settled_at = null;
                $this->loans->changeStatus($loan, LoanStatus::Active, $user, 'Reaberto por estorno do pagamento '.$payment->payment_number);
            }
            $this->collections->syncLoan($loan);

            $this->audit->log('payment.reversed', $payment, ['status' => 'confirmed'], ['status' => 'reversed', 'amounts' => $totals], $reason);
            PaymentReversed::dispatch($payment);

            return $payment;
        });
    }

    /** Prestações em aberto do plano em vigor, bloqueadas para actualização. */
    public function lockOpenInstallments(Loan $loan)
    {
        return LoanInstallment::where('loan_id', $loan->id)
            ->where('schedule_version', $loan->schedule_version)
            ->whereIn('status', InstallmentStatus::openValues())
            ->orderBy('due_date')->orderBy('number')
            ->lockForUpdate()
            ->get();
    }

    private function applyInterestWaivers(Loan $loan, $installments, array $waivers, Payment $payment): array
    {
        $applied = [];
        foreach ($installments as $installment) {
            $amount = $waivers[$installment->id] ?? null;
            if ($amount && Money::isPositive($amount)) {
                $installment->interest_waived = Money::toDb(Money::add($installment->interest_waived, $amount));
                $installment->recalculate();
                $installment->save();
                $applied[$installment->id] = $amount;
                $this->ledger->post($loan, 'interest_waiver', 'interest', 'credit', $amount, $payment, 'Juros futuros dispensados — liquidação antecipada');
            }
        }

        return $applied;
    }
}

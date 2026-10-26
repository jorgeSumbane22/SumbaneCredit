<?php

namespace App\Services\Loans;

use App\Enums\DisbursementStatus;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Events\LoanDisbursed;
use App\Exceptions\BusinessRuleException;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DocumentStorageService;
use App\Services\IdempotencyService;
use App\Services\LedgerService;
use App\Services\NumberGenerator;
use App\Services\SettingsService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Desembolsos: pedido → autorização → execução (confirmação efectiva).
 *
 * - Só empréstimos com contrato formalizado podem ser desembolsados (nunca rejeitados ou só aprovados).
 * - Um único desembolso activo por empréstimo (índice único active_loan_key).
 * - O empréstimo só passa a "activo" e o plano só é gerado quando a execução é confirmada.
 */
class DisbursementService
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly LedgerService $ledger,
        private readonly ScheduleService $schedule,
        private readonly LoanBalanceService $balances,
        private readonly LoanService $loans,
        private readonly AuditService $audit,
        private readonly SettingsService $settings,
        private readonly IdempotencyService $idempotency,
        private readonly DocumentStorageService $storage,
    ) {}

    public function request(Loan $loan, User $user, array $data, ?string $idempotencyKey = null): Disbursement
    {
        return DB::transaction(function () use ($loan, $user, $data, $idempotencyKey) {
            if (! $this->idempotency->claim($idempotencyKey, 'disbursement.request')) {
                throw new BusinessRuleException('Este pedido de desembolso já foi submetido.');
            }

            $loan = Loan::with('client')->lockForUpdate()->findOrFail($loan->id);

            if ($loan->status !== LoanStatus::Contracted) {
                $this->audit->failure('disbursement.request_denied', $loan, 'Empréstimo não formalizado: '.$loan->status->value);
                throw new BusinessRuleException('O desembolso exige um empréstimo aprovado com contrato formalizado.');
            }
            if ($this->settings->bool('finance.require_kyc_for_disbursement') && $loan->client->kyc_status !== 'verified') {
                throw new BusinessRuleException('A identificação do cliente (KYC) tem de estar verificada antes do desembolso.');
            }
            if (Disbursement::where('loan_id', $loan->id)->where('status', '!=', DisbursementStatus::Cancelled)->exists()) {
                throw new BusinessRuleException('Já existe um desembolso registado para este empréstimo.');
            }

            $gross = (string) $loan->principal_amount;
            $fees = (string) $loan->upfront_fees;

            try {
                $disbursement = Disbursement::create([
                    'disbursement_number' => $this->numbers->next('disbursement'),
                    'loan_id' => $loan->id,
                    'gross_amount' => $gross,
                    'deducted_fees' => $fees,
                    'net_amount' => Money::toDb(Money::sub($gross, $fees)),
                    'method' => PaymentMethod::from($data['method']),
                    'destination_account' => $data['destination_account'] ?? null,
                    'status' => DisbursementStatus::Pending,
                    'requested_by' => $user->id,
                    'requested_at' => now(),
                    'notes' => $data['notes'] ?? null,
                    'active_loan_key' => $loan->id,
                ]);
            } catch (QueryException $e) {
                throw new BusinessRuleException('Já existe um desembolso activo para este empréstimo.');
            }

            $this->idempotency->attach($idempotencyKey, 'disbursement', $disbursement->id);
            $this->audit->log('disbursement.requested', $disbursement, null, ['loan' => $loan->loan_number, 'net_amount' => $disbursement->net_amount]);

            return $disbursement;
        });
    }

    public function authorize(Disbursement $disbursement, User $user, ?string $notes = null): Disbursement
    {
        return DB::transaction(function () use ($disbursement, $user, $notes) {
            $disbursement = Disbursement::lockForUpdate()->findOrFail($disbursement->id);
            if ($disbursement->status !== DisbursementStatus::Pending) {
                throw new BusinessRuleException('Só desembolsos pendentes podem ser autorizados.');
            }
            if ($this->settings->bool('finance.require_distinct_disbursement_authorizer') && $disbursement->requested_by === $user->id) {
                $this->audit->failure('disbursement.authorize_denied', $disbursement, 'Autorizador igual ao requerente');
                throw new BusinessRuleException('O desembolso tem de ser autorizado por um utilizador diferente de quem o registou.');
            }
            if ($disbursement->loan->status !== LoanStatus::Contracted) {
                throw new BusinessRuleException('O empréstimo já não se encontra em condições de ser desembolsado.');
            }

            $disbursement->update([
                'status' => DisbursementStatus::Authorized,
                'authorized_by' => $user->id,
                'authorized_at' => now(),
                'notes' => trim(($disbursement->notes ? $disbursement->notes."\n" : '').($notes ?? '')) ?: null,
            ]);
            $this->audit->log('disbursement.authorized', $disbursement, ['status' => 'pending'], ['status' => 'authorized']);

            return $disbursement;
        });
    }

    /** Confirma a execução efectiva da transferência/entrega dos fundos. */
    public function complete(Disbursement $disbursement, User $user, array $data, ?UploadedFile $proof = null): Disbursement
    {
        return DB::transaction(function () use ($disbursement, $user, $data, $proof) {
            $disbursement = Disbursement::lockForUpdate()->findOrFail($disbursement->id);
            if ($disbursement->status !== DisbursementStatus::Authorized) {
                throw new BusinessRuleException('Só desembolsos autorizados podem ser confirmados como efectuados.');
            }
            $loan = Loan::lockForUpdate()->findOrFail($disbursement->loan_id);
            if ($loan->status !== LoanStatus::Contracted) {
                throw new BusinessRuleException('O empréstimo não se encontra formalizado.');
            }
            if ($disbursement->method->requiresReference() && empty($data['reference'])) {
                throw new BusinessRuleException('Indique a referência da operação de desembolso.');
            }

            $date = CarbonImmutable::parse($data['disbursed_at'] ?? today())->startOfDay();
            if ($date->gt(CarbonImmutable::today())) {
                throw new BusinessRuleException('A data do desembolso não pode ser futura.');
            }

            try {
                $disbursement->fill([
                    'reference' => $data['reference'] ?? null,
                    'status' => DisbursementStatus::Completed,
                    'executed_by' => $user->id,
                    'disbursed_at' => $date->toDateString(),
                    'completed_at' => now(),
                    'proof_path' => $proof ? $this->storage->store($proof, "loans/{$loan->id}/disbursement")['path'] : null,
                ])->save();
            } catch (QueryException) {
                throw new BusinessRuleException('A referência indicada já foi utilizada noutro desembolso.');
            }

            // Plano de amortização a partir da data efectiva do desembolso.
            $schedule = $this->schedule->generate($loan, $this->schedule->contractTerms($loan), $date, $loan->schedule_version);

            $loan->fill([
                'disbursed_at' => $date->toDateString(),
                'first_due_date' => $schedule['first_due_date'],
                'maturity_date' => $schedule['maturity_date'],
                'total_interest' => $schedule['totals']['interest'],
                'total_contract_amount' => $schedule['totals']['installments'],
            ]);
            $this->loans->changeStatus($loan, LoanStatus::Active, $user, 'Desembolso '.$disbursement->disbursement_number.' efectuado.');

            // Razão: valores contratados em dívida e saída de fundos.
            $desc = 'Desembolso '.$disbursement->disbursement_number;
            $this->ledger->post($loan, 'disbursement', 'principal', 'debit', (string) $loan->principal_amount, $disbursement, $desc, $date);
            $this->ledger->post($loan, 'interest_charge', 'interest', 'debit', $schedule['totals']['interest'], $disbursement, 'Juros contratuais do plano', $date);
            $this->ledger->post($loan, 'fee_charge', 'fee', 'debit', $schedule['totals']['fees_financed'], $disbursement, 'Encargos financiados', $date);
            if (Money::isPositive($disbursement->deducted_fees)) {
                $this->ledger->post($loan, 'fee_charge', 'fee', 'debit', (string) $disbursement->deducted_fees, $disbursement, 'Encargos cobrados no desembolso', $date);
                $this->ledger->post($loan, 'upfront_fee_deduction', 'fee', 'credit', (string) $disbursement->deducted_fees, $disbursement, 'Encargos deduzidos ao valor desembolsado', $date);
            }
            $this->ledger->post($loan, 'disbursement_cash_out', 'cash', 'credit', (string) $disbursement->net_amount, $disbursement, 'Valor líquido entregue ao cliente', $date);

            $this->balances->refresh($loan);
            $this->audit->log('disbursement.completed', $disbursement, ['status' => 'authorized'], [
                'status' => 'completed', 'reference' => $disbursement->reference, 'disbursed_at' => $date->toDateString(),
            ]);

            LoanDisbursed::dispatch($loan, $disbursement);

            return $disbursement;
        });
    }

    public function cancel(Disbursement $disbursement, User $user, string $reason): Disbursement
    {
        return DB::transaction(function () use ($disbursement, $user, $reason) {
            $disbursement = Disbursement::lockForUpdate()->findOrFail($disbursement->id);
            if (! in_array($disbursement->status, [DisbursementStatus::Pending, DisbursementStatus::Authorized], true)) {
                throw new BusinessRuleException('Um desembolso efectuado não pode ser cancelado.');
            }
            $from = $disbursement->status->value;
            $disbursement->update(['status' => DisbursementStatus::Cancelled, 'active_loan_key' => null, 'cancellation_reason' => $reason]);
            $this->audit->log('disbursement.cancelled', $disbursement, ['status' => $from], ['status' => 'cancelled'], $reason);

            return $disbursement;
        });
    }
}

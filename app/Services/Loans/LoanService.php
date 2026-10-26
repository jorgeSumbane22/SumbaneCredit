<?php

namespace App\Services\Loans;

use App\Enums\DisbursementStatus;
use App\Enums\LoanStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Credit\LoanSimulationService;
use App\Services\DocumentStorageService;
use App\Services\Finance\InterestCalculator;
use App\Services\NumberGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class LoanService
{
    public function __construct(
        private readonly LoanSimulationService $simulation,
        private readonly InterestCalculator $interest,
        private readonly NumberGenerator $numbers,
        private readonly AuditService $audit,
        private readonly DocumentStorageService $storage,
    ) {}

    /**
     * Cria o empréstimo (estado "aguarda formalização") a partir de um pedido aprovado,
     * copiando de forma imutável as condições da versão do produto em vigor no pedido.
     */
    public function createFromApplication(LoanApplication $application, User $approver): Loan
    {
        if ($application->status->value !== 'approved') {
            throw new BusinessRuleException('Só é possível criar empréstimos a partir de pedidos aprovados.');
        }
        if (Loan::where('loan_application_id', $application->id)->exists()) {
            throw new BusinessRuleException('Já existe um empréstimo para este pedido.');
        }

        $version = $application->productVersion;
        $terms = $this->simulation->terms($version, (string) $application->approved_amount, (int) $application->approved_term,
            $application->repayment_frequency->value, $application->amortization_method->value);
        $preview = $this->simulation->simulate($version, $terms->principal, $terms->term, $terms->frequency->value, $terms->method->value);

        $loan = Loan::create([
            'loan_number' => $this->numbers->next('loan'),
            'contract_number' => $this->numbers->next('contract'),
            'client_id' => $application->client_id,
            'loan_application_id' => $application->id,
            'loan_product_id' => $application->loan_product_id,
            'loan_product_version_id' => $version->id,
            'principal_amount' => $terms->principal,
            'interest_rate' => $version->interest_rate,
            'rate_period' => $version->rate_period,
            'rate_per_period' => $this->interest->periodRate((string) $version->interest_rate, $version->rate_period, $terms->frequency),
            'repayment_frequency' => $terms->frequency,
            'amortization_method' => $terms->method,
            'term' => $terms->term,
            'fees' => $version->fees ?? [],
            'upfront_fees' => $preview['totals']['fees_upfront'],
            'financed_fees' => $preview['totals']['fees_financed'],
            'total_interest' => $preview['totals']['interest'],
            'total_contract_amount' => $preview['totals']['installments'],
            'penalty_type' => $version->penalty_type,
            'penalty_value' => $version->penalty_value,
            'penalty_grace_days' => $version->penalty_grace_days,
            'early_settlement_rule' => $version->early_settlement_rule,
            'terms_snapshot' => [
                'product' => ['code' => $version->product->code, 'name' => $version->product->name, 'version' => $version->version],
                'contract_conditions' => $version->product->contract_conditions,
                'interest_rate' => (string) $version->interest_rate,
                'rate_period' => $version->rate_period,
                'rate_label' => $version->rateLabel(),
                'frequency' => $terms->frequency->value,
                'method' => $terms->method->value,
                'fees' => $preview['fees'],
                'fees_label' => $version->feesLabel(),
                'penalty_label' => $version->penaltyLabel(),
                'early_settlement_label' => $version->earlySettlementLabel(),
                'preview_installment' => $preview['max_installment'],
                'approved_at' => now()->toIso8601String(),
            ],
            'status' => LoanStatus::AwaitingContract,
            'principal_outstanding' => '0.00',
            'total_outstanding' => '0.00',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'loan_officer_id' => $application->assigned_to,
        ]);

        $application->guarantors()->update(['loan_id' => $loan->id]);
        $application->collaterals()->update(['loan_id' => $loan->id]);

        $loan->statusHistory()->create(['to_status' => LoanStatus::AwaitingContract->value, 'user_id' => $approver->id, 'reason' => 'Empréstimo criado a partir do pedido aprovado '.$application->application_number]);
        $this->audit->log('loan.created', $loan, null, ['principal' => $loan->principal_amount, 'term' => $loan->term]);

        return $loan;
    }

    public function changeStatus(Loan $loan, LoanStatus $to, User $user, ?string $reason = null): void
    {
        $from = $loan->status;
        if (! $from->canTransitionTo($to)) {
            $this->audit->failure('loan.transition_denied', $loan, "Transição inválida: {$from->value} → {$to->value}");
            throw new BusinessRuleException("Não é possível passar o empréstimo de \"{$from->label()}\" para \"{$to->label()}\".");
        }
        $loan->status = $to;
        $loan->save();
        $loan->statusHistory()->create(['from_status' => $from->value, 'to_status' => $to->value, 'user_id' => $user->id, 'reason' => $reason]);
        $this->audit->log('loan.status_changed', $loan, ['status' => $from->value], ['status' => $to->value], $reason);
    }

    /** Formalização contratual: regista a assinatura do contrato pelo cliente. */
    public function formalize(Loan $loan, User $user, string $signedAt, ?UploadedFile $signedContract, ?string $notes): Loan
    {
        return DB::transaction(function () use ($loan, $user, $signedAt, $signedContract, $notes) {
            $loan = Loan::lockForUpdate()->findOrFail($loan->id);
            if ($loan->status !== LoanStatus::AwaitingContract) {
                throw new BusinessRuleException('O contrato só pode ser formalizado quando o empréstimo aguarda formalização.');
            }
            if ($signedContract) {
                $loan->signed_contract_path = $this->storage->store($signedContract, "loans/{$loan->id}/contract")['path'];
            }
            $loan->contract_signed_at = $signedAt;
            $loan->formalized_by = $user->id;
            $this->changeStatus($loan, LoanStatus::Contracted, $user, $notes ?: 'Contrato assinado e formalizado.');

            return $loan;
        });
    }

    public function cancel(Loan $loan, User $user, string $reason): Loan
    {
        return DB::transaction(function () use ($loan, $user, $reason) {
            $loan = Loan::lockForUpdate()->findOrFail($loan->id);
            if ($loan->disbursements()->where('status', DisbursementStatus::Completed)->exists()) {
                throw new BusinessRuleException('Um empréstimo desembolsado não pode ser cancelado.');
            }
            $loan->disbursements()->whereIn('status', ['pending', 'authorized'])->get()->each(function ($d) use ($reason) {
                $d->update(['status' => DisbursementStatus::Cancelled, 'active_loan_key' => null, 'cancellation_reason' => $reason]);
            });
            $loan->cancelled_at = now();
            $this->changeStatus($loan, LoanStatus::Cancelled, $user, $reason);

            return $loan;
        });
    }
}

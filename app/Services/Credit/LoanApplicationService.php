<?php

namespace App\Services\Credit;

use App\Enums\ApplicationStatus;
use App\Enums\LoanStatus;
use App\Events\ApplicationInformationProvided;
use App\Events\ApplicationInformationRequested;
use App\Events\LoanApplicationApproved;
use App\Events\LoanApplicationRejected;
use App\Events\LoanApplicationSubmitted;
use App\Exceptions\BusinessRuleException;
use App\Models\Client;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DocumentStorageService;
use App\Services\Loans\LoanService;
use App\Services\NumberGenerator;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de vida do pedido de crédito. Todas as transições são validadas pela máquina de
 * estados de ApplicationStatus e registadas no histórico (loan_application_reviews).
 */
class LoanApplicationService
{
    public function __construct(
        private readonly LoanSimulationService $simulation,
        private readonly NumberGenerator $numbers,
        private readonly DocumentStorageService $documents,
        private readonly LoanService $loans,
        private readonly AuditService $audit,
    ) {}

    public function submit(Client $client, array $data, User $by): LoanApplication
    {
        if ($client->status !== 'active') {
            throw new BusinessRuleException('A conta do cliente não está activa. Contacte a instituição.');
        }

        $product = LoanProduct::active()->with('currentVersion')->findOrFail($data['loan_product_id']);
        $version = $product->currentVersion;

        if (empty($data['declaration']) || empty($data['consent_data_processing']) || empty($data['consent_credit_check'])) {
            throw new BusinessRuleException('É necessário aceitar a declaração de veracidade e os consentimentos.');
        }

        // Valida limites do produto e obtém a simulação (mesmo motor dos planos reais).
        $simulation = $this->simulation->simulate($version, (string) $data['amount'], (int) $data['term'], $data['frequency'] ?? null, $data['method'] ?? null);

        $this->checkEligibility($client, $version, $data);

        return DB::transaction(function () use ($client, $data, $by, $product, $version, $simulation) {
            // Serializa submissões concorrentes do mesmo cliente.
            Client::whereKey($client->id)->lockForUpdate()->first();
            if (LoanApplication::where('client_id', $client->id)->open()->exists()) {
                throw new BusinessRuleException('Já existe um pedido em análise. Aguarde a decisão ou cancele-o antes de submeter outro.');
            }

            $application = LoanApplication::create([
                'application_number' => $this->numbers->next('application'),
                'client_id' => $client->id,
                'loan_product_id' => $product->id,
                'loan_product_version_id' => $version->id,
                'requested_amount' => $simulation['totals']['principal'],
                'term' => $simulation['conditions']['term'],
                'repayment_frequency' => $simulation['conditions']['frequency'],
                'amortization_method' => $simulation['conditions']['method'],
                'purpose' => $data['purpose'],
                'monthly_income' => Money::toDb($data['monthly_income']),
                'other_income' => Money::toDb($data['other_income'] ?? '0'),
                'monthly_expenses' => Money::toDb($data['monthly_expenses'] ?? '0'),
                'existing_obligations' => Money::toDb($data['existing_obligations'] ?? '0'),
                'employment_type' => $data['employment_type'] ?? null,
                'employer_name' => $data['employer_name'] ?? null,
                'occupation' => $data['occupation'] ?? null,
                'simulated_installment' => $simulation['max_installment'],
                'simulated_total' => $simulation['totals']['installments'],
                'status' => ApplicationStatus::Submitted,
                'consents' => [
                    'data_processing' => true,
                    'credit_check' => true,
                    'accepted_at' => now()->toIso8601String(),
                    'ip' => request()?->ip(),
                ],
                'declaration_accepted_at' => now(),
                'submitted_at' => now(),
                'created_by' => $by->id,
            ]);

            foreach ($data['guarantors'] ?? [] as $g) {
                if (! empty($g['full_name'])) {
                    $application->guarantors()->create(array_intersect_key($g, array_flip([
                        'full_name', 'document_type', 'document_number', 'phone', 'relationship', 'occupation', 'monthly_income', 'address',
                    ])));
                }
            }
            foreach ($data['collaterals'] ?? [] as $c) {
                if (! empty($c['description'])) {
                    $application->collaterals()->create([
                        'type' => $c['type'] ?? 'other',
                        'description' => $c['description'],
                        'estimated_value' => Money::toDb($c['estimated_value'] ?? '0'),
                    ]);
                }
            }

            $this->attachDocuments($application, $client, $data);

            $application->reviews()->create([
                'user_id' => $by->id, 'action' => 'submitted', 'to_status' => ApplicationStatus::Submitted->value,
                'notes' => 'Pedido submetido. Simulação: prestação '.Money::format($simulation['max_installment']).', total '.Money::format($simulation['totals']['installments']).'.',
            ]);

            // Actualiza os dados profissionais do cliente com a informação declarada.
            $client->employment()->updateOrCreate([], array_filter([
                'employment_type' => $data['employment_type'] ?? null,
                'employer_name' => $data['employer_name'] ?? null,
                'monthly_income' => Money::toDb($data['monthly_income']),
                'other_income' => Money::toDb($data['other_income'] ?? '0'),
                'monthly_expenses' => Money::toDb($data['monthly_expenses'] ?? '0'),
            ], fn ($v) => $v !== null));

            $this->audit->log('application.submitted', $application, null, [
                'amount' => $application->requested_amount, 'term' => $application->term, 'product' => $product->code,
            ]);

            LoanApplicationSubmitted::dispatch($application);

            return $application;
        });
    }

    private function checkEligibility(Client $client, $version, array $data): void
    {
        $minIncome = $version->eligibility('min_monthly_income');
        $income = Money::add($data['monthly_income'] ?? '0', $data['other_income'] ?? '0');
        if ($minIncome && Money::lt($income, (string) $minIncome)) {
            throw new BusinessRuleException('O rendimento declarado é inferior ao mínimo exigido para este produto ('.Money::format($minIncome).').');
        }

        $maxActive = $version->eligibility('max_active_loans');
        if ($maxActive !== null && Loan::where('client_id', $client->id)->whereIn('status', [LoanStatus::Active, LoanStatus::AwaitingContract, LoanStatus::Contracted])->count() >= (int) $maxActive) {
            throw new BusinessRuleException('Atingiu o número máximo de empréstimos activos permitido para este produto.');
        }

        if ($client->birth_date && $client->birth_date->age < (int) $version->eligibility('min_age', 18)) {
            throw new BusinessRuleException('Não cumpre a idade mínima exigida para este produto.');
        }

        if ($version->eligibility('requires_guarantor') && collect($data['guarantors'] ?? [])->filter(fn ($g) => ! empty($g['full_name']))->isEmpty()) {
            throw new BusinessRuleException('Este produto exige a indicação de pelo menos um fiador.');
        }

        if ($version->eligibility('requires_collateral') && collect($data['collaterals'] ?? [])->filter(fn ($c) => ! empty($c['description']))->isEmpty()) {
            throw new BusinessRuleException('Este produto exige a indicação de uma garantia.');
        }
    }

    private function attachDocuments(LoanApplication $application, Client $client, array $data): void
    {
        $ids = [];
        // Apenas documentos do próprio cliente podem ser associados (protecção contra alteração de identificadores).
        if (! empty($data['document_ids'])) {
            $ids = $client->documents()->whereIn('id', $data['document_ids'])->where('status', '!=', 'rejected')->pluck('id')->all();
        }
        foreach ($data['new_documents'] ?? [] as $doc) {
            if (! empty($doc['file'])) {
                $ids[] = $this->documents->storeClientDocument($client, $doc['file'], $doc['type'] ?? 'other')->id;
            }
        }
        if ($ids) {
            $application->documents()->syncWithoutDetaching($ids);
        }
    }

    public function transition(LoanApplication $application, ApplicationStatus $to, User $user, string $action, ?string $notes = null, array $data = []): LoanApplication
    {
        $from = $application->status;
        if (! $from->canTransitionTo($to)) {
            $this->audit->failure('application.transition_denied', $application, "Transição inválida: {$from->value} → {$to->value}");
            throw new BusinessRuleException("Não é possível passar o pedido de \"{$from->label()}\" para \"{$to->label()}\".");
        }

        $application->status = $to;
        $application->save();

        $application->reviews()->create([
            'user_id' => $user->id, 'action' => $action, 'from_status' => $from->value, 'to_status' => $to->value,
            'notes' => $notes, 'data' => $data ?: null,
        ]);
        $this->audit->log("application.{$action}", $application, ['status' => $from->value], ['status' => $to->value], $notes);

        return $application;
    }

    public function startReview(LoanApplication $application, User $user): LoanApplication
    {
        return DB::transaction(function () use ($application, $user) {
            $application = LoanApplication::lockForUpdate()->findOrFail($application->id);
            $application->assigned_to ??= $user->id;
            if ($application->status === ApplicationStatus::UnderReview) {
                $application->save();

                return $application;
            }

            return $this->transition($application, ApplicationStatus::UnderReview, $user, 'review_started', 'Análise iniciada por '.$user->name.'.');
        });
    }

    public function assign(LoanApplication $application, User $assignee, User $by): void
    {
        $application->update(['assigned_to' => $assignee->id]);
        $application->reviews()->create(['user_id' => $by->id, 'action' => 'assigned', 'notes' => 'Atribuído a '.$assignee->name.'.']);
        $this->audit->log('application.assigned', $application, null, ['assigned_to' => $assignee->id]);
    }

    public function addNote(LoanApplication $application, User $user, string $notes): void
    {
        $application->reviews()->create(['user_id' => $user->id, 'action' => 'note', 'notes' => $notes]);
        $this->audit->log('application.note', $application, null, null, 'Observação registada');
    }

    public function requestInformation(LoanApplication $application, User $user, string $message): LoanApplication
    {
        return DB::transaction(function () use ($application, $user, $message) {
            $application = LoanApplication::lockForUpdate()->findOrFail($application->id);
            $application->info_request = $message;
            $this->transition($application, ApplicationStatus::PendingDocuments, $user, 'info_requested', $message);
            ApplicationInformationRequested::dispatch($application);

            return $application;
        });
    }

    public function provideInformation(LoanApplication $application, User $user, ?string $message, array $newDocuments): LoanApplication
    {
        return DB::transaction(function () use ($application, $user, $message, $newDocuments) {
            $application = LoanApplication::lockForUpdate()->findOrFail($application->id);
            if ($application->status !== ApplicationStatus::PendingDocuments) {
                throw new BusinessRuleException('O pedido não está a aguardar informação adicional.');
            }
            if (! $message && empty($newDocuments)) {
                throw new BusinessRuleException('Indique a informação solicitada ou anexe documentos.');
            }
            $this->attachDocuments($application, $application->client, ['new_documents' => $newDocuments]);
            $application->info_request = null;
            $this->transition($application, ApplicationStatus::UnderReview, $user, 'info_provided', $message ?: 'Documentos adicionais enviados.');
            ApplicationInformationProvided::dispatch($application);

            return $application;
        });
    }

    public function forward(LoanApplication $application, User $user, ?string $notes): LoanApplication
    {
        return DB::transaction(function () use ($application, $user, $notes) {
            $application = LoanApplication::lockForUpdate()->findOrFail($application->id);
            if (! $application->assessments()->exists()) {
                throw new BusinessRuleException('Execute a avaliação de crédito antes de encaminhar o pedido para decisão.');
            }

            return $this->transition($application, ApplicationStatus::Forwarded, $user, 'forwarded', $notes ?: 'Encaminhado para decisão.');
        });
    }

    public function approve(LoanApplication $application, User $user, string $amount, int $term, ?string $notes): Loan
    {
        return DB::transaction(function () use ($application, $user, $amount, $term, $notes) {
            $application = LoanApplication::with('productVersion.product')->lockForUpdate()->findOrFail($application->id);

            if (! $application->assessments()->exists()) {
                throw new BusinessRuleException('O pedido não pode ser aprovado sem avaliação de crédito registada.');
            }
            if (Money::gt($amount, $application->requested_amount)) {
                throw new BusinessRuleException('O montante aprovado não pode exceder o montante solicitado.');
            }
            if ($application->client->status !== 'active') {
                throw new BusinessRuleException('A conta do cliente não está activa.');
            }
            // Valida os limites do produto (versão do pedido).
            $this->simulation->terms($application->productVersion, $amount, $term, $application->repayment_frequency->value, $application->amortization_method->value);

            $application->approved_amount = Money::toDb($amount);
            $application->approved_term = $term;
            $application->decided_by = $user->id;
            $application->decided_at = now();
            $application->decision_notes = $notes;
            $application->info_request = null;
            $this->transition($application, ApplicationStatus::Approved, $user, 'approved',
                'Aprovado: '.Money::format($amount)." em {$term} prestações.".($notes ? " {$notes}" : ''),
                ['approved_amount' => Money::toDb($amount), 'approved_term' => $term]);

            $loan = $this->loans->createFromApplication($application, $user);

            LoanApplicationApproved::dispatch($application, $loan);

            return $loan;
        });
    }

    public function reject(LoanApplication $application, User $user, string $reason): LoanApplication
    {
        return DB::transaction(function () use ($application, $user, $reason) {
            $application = LoanApplication::lockForUpdate()->findOrFail($application->id);
            $application->decided_by = $user->id;
            $application->decided_at = now();
            $application->rejection_reason = $reason;
            $this->transition($application, ApplicationStatus::Rejected, $user, 'rejected', $reason);
            LoanApplicationRejected::dispatch($application);

            return $application;
        });
    }

    public function cancel(LoanApplication $application, User $user, ?string $reason): LoanApplication
    {
        return DB::transaction(function () use ($application, $user, $reason) {
            $application = LoanApplication::lockForUpdate()->findOrFail($application->id);
            if ($user->isClient() && ! $application->status->clientCanCancel()) {
                throw new BusinessRuleException('Este pedido já não pode ser cancelado pelo cliente.');
            }
            $application->cancelled_at = now();

            return $this->transition($application, ApplicationStatus::Cancelled, $user, 'cancelled', $reason ?: 'Pedido cancelado.');
        });
    }
}

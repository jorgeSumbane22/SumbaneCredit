<?php

namespace App\Services\Credit;

use App\Enums\LoanStatus;
use App\Models\CreditAssessment;
use App\Models\CreditAssessmentRuleSet;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanInstallment;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Finance\CreditAssessmentEngine;
use App\Support\Money;

class CreditAssessmentService
{
    public function __construct(
        private readonly CreditAssessmentEngine $engine,
        private readonly LoanSimulationService $simulation,
        private readonly AuditService $audit,
    ) {}

    public function activeRules(): array
    {
        $set = CreditAssessmentRuleSet::active();

        return [
            'set' => $set,
            'version' => $set?->version ?? 0,
            'rules' => array_merge(CreditAssessmentEngine::DEFAULT_RULES, $set?->rules ?? []),
        ];
    }

    public function assess(LoanApplication $application, User $user): CreditAssessment
    {
        $application->loadMissing('productVersion', 'client');
        $client = $application->client;
        $amount = (string) ($application->approved_amount ?? $application->requested_amount);
        $term = (int) ($application->approved_term ?? $application->term);

        $simulation = $this->simulation->simulate(
            $application->productVersion, $amount, $term,
            $application->repayment_frequency->value, $application->amortization_method->value,
        );

        $loanIds = Loan::where('client_id', $client->id)
            ->whereNotIn('status', [LoanStatus::Cancelled, LoanStatus::AwaitingContract, LoanStatus::Contracted])
            ->pluck('id');
        $worst = $loanIds->isEmpty() ? null : (int) LoanInstallment::whereIn('loan_id', $loanIds)->max('days_overdue');

        $inputs = [
            'monthly_income' => (string) $application->monthly_income,
            'other_income' => (string) $application->other_income,
            'monthly_expenses' => (string) $application->monthly_expenses,
            'existing_obligations' => (string) $application->existing_obligations,
            'installment' => $simulation['max_installment'],
            'frequency' => $application->repayment_frequency,
            'principal' => Money::toDb($amount),
            'outstanding_exposure' => Money::toDb((string) Loan::where('client_id', $client->id)->where('status', LoanStatus::Active)->sum('principal_outstanding')),
            'active_loans' => Loan::where('client_id', $client->id)->where('status', LoanStatus::Active)->count(),
            'worst_days_overdue' => $worst,
            'previous_loans' => $loanIds->count(),
        ];

        $rules = $this->activeRules();
        $result = $this->engine->assess($inputs, $rules['rules']);

        $assessment = CreditAssessment::create([
            'loan_application_id' => $application->id,
            'rule_set_id' => $rules['set']?->id,
            'rules_version' => $rules['version'],
            'rules' => $rules['rules'],
            'inputs' => $result['inputs'],
            'indicators' => $result['indicators'],
            'result' => $result['result'],
            'explanation' => $result['explanation'],
            'performed_by' => $user->id,
        ]);

        $application->reviews()->create([
            'user_id' => $user->id,
            'action' => 'assessment',
            'notes' => $assessment->resultLabel().'. '.$result['explanation'],
            'data' => ['assessment_id' => $assessment->id, 'result' => $result['result'], 'rules_version' => $rules['version']],
        ]);

        $this->audit->log('application.assessed', $application, null, ['result' => $result['result'], 'rules_version' => $rules['version']]);

        return $assessment;
    }
}

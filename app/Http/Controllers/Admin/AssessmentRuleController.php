<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditAssessmentRuleSet;
use App\Services\AuditService;
use App\Services\Finance\CreditAssessmentEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Regras da avaliação semiautomática. Cada alteração cria uma nova versão (proposta pela
 * administração); a versão só entra em vigor após validação por um gestor com poder de decisão.
 */
class AssessmentRuleController extends Controller
{
    public const LABELS = [
        'installment_to_income_favorable' => ['Prestação/rendimento — favorável até (%)', 'percent'],
        'installment_to_income_review' => ['Prestação/rendimento — análise adicional até (%)', 'percent'],
        'debt_service_favorable' => ['Taxa de esforço total — favorável até (%)', 'percent'],
        'debt_service_review' => ['Taxa de esforço total — análise adicional até (%)', 'percent'],
        'min_residual_income' => ['Rendimento disponível mínimo após a prestação (MZN)', 'money'],
        'history_days_overdue_favorable' => ['Pior atraso histórico — favorável até (dias)', 'days'],
        'history_days_overdue_review' => ['Pior atraso histórico — análise adicional até (dias)', 'days'],
        'exposure_to_income_review' => ['Exposição/rendimento — análise adicional acima de (×)', 'ratio'],
        'exposure_to_income_unfavorable' => ['Exposição/rendimento — desfavorável acima de (×)', 'ratio'],
        'max_active_loans' => ['N.º de empréstimos activos que exige análise', 'count'],
    ];

    public function __construct(private readonly AuditService $audit) {}

    public function index(): View
    {
        $sets = CreditAssessmentRuleSet::with('creator', 'validator')->orderByDesc('version')->get();
        $active = $sets->firstWhere('is_active', true);

        return view('admin.assessment-rules.index', [
            'sets' => $sets,
            'active' => $active,
            'current' => array_merge(CreditAssessmentEngine::DEFAULT_RULES, $active?->rules ?? $sets->first()?->rules ?? []),
            'labels' => self::LABELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (array_keys(self::LABELS) as $key) {
            $rules["rules.{$key}"] = ['required', 'numeric', 'min:0', 'max:100000000'];
        }
        $data = $request->validate($rules + ['name' => ['required', 'string', 'max:120'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $r = $data['rules'];
        if ($r['installment_to_income_favorable'] > $r['installment_to_income_review'] || $r['debt_service_favorable'] > $r['debt_service_review']
            || $r['history_days_overdue_favorable'] > $r['history_days_overdue_review'] || $r['exposure_to_income_review'] > $r['exposure_to_income_unfavorable']) {
            return back()->withInput()->with('error', 'Os limiares "favorável" não podem exceder os de "análise adicional".');
        }

        $set = CreditAssessmentRuleSet::create([
            'version' => (int) CreditAssessmentRuleSet::max('version') + 1,
            'name' => $data['name'],
            'rules' => array_map(fn ($v) => $v + 0, $r),
            'is_active' => false,
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);
        $this->audit->log('assessment_rules.created', $set, null, $set->rules);

        return back()->with('success', 'Versão '.$set->version.' criada. Fica em vigor após validação por um gestor de crédito.');
    }

    public function activate(Request $request, CreditAssessmentRuleSet $ruleSet): RedirectResponse
    {
        DB::transaction(function () use ($request, $ruleSet) {
            CreditAssessmentRuleSet::where('is_active', true)->update(['is_active' => false]);
            $ruleSet->update(['is_active' => true, 'validated_by' => $request->user()->id, 'validated_at' => now()]);
        });
        $this->audit->log('assessment_rules.validated', $ruleSet, null, ['version' => $ruleSet->version]);

        return back()->with('success', 'Regras v'.$ruleSet->version.' validadas e em vigor.');
    }
}

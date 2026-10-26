<?php

namespace App\Services\Finance;

use App\Enums\RepaymentFrequency;
use App\Support\Money;

/**
 * Motor de apoio à decisão de crédito, transparente e explicável.
 *
 * NÃO é um modelo de risco validado. Os limiares vêm de um conjunto de regras versionado,
 * configurado e validado pela instituição. Cada indicador devolve o valor calculado, os
 * limiares aplicados e uma justificação. Não usa atributos sensíveis (género, religião,
 * etnia, estado civil, etc.).
 *
 * Resultado: "favorable" (indicadores favoráveis), "review" (necessita análise adicional)
 * ou "unfavorable" (indicadores desfavoráveis). A decisão final é sempre humana.
 */
final class CreditAssessmentEngine
{
    public const DEFAULT_RULES = [
        'installment_to_income_favorable' => 30,   // % máx. da prestação no rendimento (favorável)
        'installment_to_income_review' => 40,      // % máx. (análise adicional); acima = desfavorável
        'debt_service_favorable' => 40,            // % (obrigações + prestação) / rendimento
        'debt_service_review' => 55,
        'min_residual_income' => 0,                // rendimento disponível mínimo após a prestação (MZN)
        'history_days_overdue_favorable' => 15,    // pior atraso histórico (dias)
        'history_days_overdue_review' => 60,
        'exposure_to_income_review' => 6,          // (capital em dívida + novo capital) / rendimento mensal
        'exposure_to_income_unfavorable' => 12,
        'max_active_loans' => 2,
    ];

    /**
     * @param  array{monthly_income:string, other_income:string, monthly_expenses:string, existing_obligations:string, installment:string, frequency:RepaymentFrequency, principal:string, outstanding_exposure:string, active_loans:int, worst_days_overdue:?int, previous_loans:int}  $in
     * @return array{result:string, indicators: array<int, array{key:string,label:string,value:string,threshold:string,status:string,explanation:string}>, explanation:string, inputs: array}
     */
    public function assess(array $in, array $rules): array
    {
        $rules = array_merge(self::DEFAULT_RULES, $rules);
        $income = Money::add($in['monthly_income'], $in['other_income']);
        $monthlyInstallment = $this->monthlyEquivalent($in['installment'], $in['frequency']);
        $indicators = [];

        if (! Money::isPositive($income)) {
            $indicators[] = $this->indicator('income', 'Rendimento declarado', '0,00 MZN', '> 0', 'unfavorable',
                'Não foi declarado rendimento mensal; não é possível avaliar a capacidade de pagamento.');

            return $this->finish($indicators, $in, $monthlyInstallment, $income);
        }

        // 1. Relação prestação / rendimento
        $pti = Money::round(Money::mul(Money::div($monthlyInstallment, $income), '100'));
        $indicators[] = $this->thresholdIndicator('installment_to_income', 'Relação prestação/rendimento', $pti,
            $rules['installment_to_income_favorable'], $rules['installment_to_income_review'], '%',
            "A prestação mensal equivalente (".Money::format($monthlyInstallment).") representa {$this->pct($pti)} do rendimento.");

        // 2. Taxa de esforço total (obrigações existentes + nova prestação)
        $debt = Money::add($in['existing_obligations'], $monthlyInstallment);
        $dsr = Money::round(Money::mul(Money::div($debt, $income), '100'));
        $indicators[] = $this->thresholdIndicator('debt_service', 'Taxa de esforço total', $dsr,
            $rules['debt_service_favorable'], $rules['debt_service_review'], '%',
            "Obrigações mensais (".Money::format($in['existing_obligations']).") mais a nova prestação representam {$this->pct($dsr)} do rendimento.");

        // 3. Rendimento disponível após despesas, obrigações e prestação
        $disposable = Money::toDb(Money::sub(Money::sub($income, $in['monthly_expenses']), $in['existing_obligations']));
        $residual = Money::toDb(Money::sub($disposable, $monthlyInstallment));
        $minResidual = Money::of($rules['min_residual_income']);
        $residualStatus = Money::lt($residual, '0') ? 'unfavorable' : (Money::lt($residual, $minResidual) ? 'review' : 'favorable');
        $indicators[] = $this->indicator('residual_income', 'Rendimento disponível após a prestação', Money::format($residual),
            '≥ '.Money::format($minResidual), $residualStatus,
            'Rendimento disponível estimado de '.Money::format($disposable).' (rendimento − despesas − obrigações); após a prestação restam '.Money::format($residual).'.');

        // 4. Histórico interno de pagamentos
        if ($in['previous_loans'] === 0 || $in['worst_days_overdue'] === null) {
            $indicators[] = $this->indicator('history', 'Histórico interno de pagamentos', 'Sem histórico', '—', 'review',
                'O cliente não tem empréstimos anteriores na instituição; não há histórico interno para avaliar.');
        } else {
            $days = (string) $in['worst_days_overdue'];
            $indicators[] = $this->thresholdIndicator('history', 'Pior atraso histórico', $days,
                $rules['history_days_overdue_favorable'], $rules['history_days_overdue_review'], ' dias',
                "Em {$in['previous_loans']} empréstimo(s) anterior(es)/actual(is), o maior atraso registado foi de {$days} dia(s).");
        }

        // 5. Exposição financeira na instituição
        $exposure = Money::add($in['outstanding_exposure'], $in['principal']);
        $ratio = Money::round(Money::div($exposure, $income), 2);
        $exposureStatus = Money::gt($ratio, $rules['exposure_to_income_unfavorable']) ? 'unfavorable'
            : (Money::gt($ratio, $rules['exposure_to_income_review']) ? 'review' : 'favorable');
        $indicators[] = $this->indicator('exposure', 'Exposição total / rendimento mensal', str_replace('.', ',', $ratio).'×',
            '≤ '.$rules['exposure_to_income_review'].'× (análise ≤ '.$rules['exposure_to_income_unfavorable'].'×)', $exposureStatus,
            'Capital em dívida actual ('.Money::format($in['outstanding_exposure']).') mais o novo capital ('.Money::format($in['principal']).') equivale a '.str_replace('.', ',', $ratio).' vezes o rendimento mensal.');

        // 6. Número de empréstimos activos
        $activeStatus = $in['active_loans'] >= (int) $rules['max_active_loans'] ? 'review' : 'favorable';
        $indicators[] = $this->indicator('active_loans', 'Empréstimos activos', (string) $in['active_loans'],
            '< '.$rules['max_active_loans'], $activeStatus,
            "O cliente tem {$in['active_loans']} empréstimo(s) activo(s) na instituição.");

        return $this->finish($indicators, $in, $monthlyInstallment, $income);
    }

    public function monthlyEquivalent(string $installment, RepaymentFrequency $frequency): string
    {
        return Money::round(Money::div(Money::mul($installment, (string) $frequency->periodsPerYear()), '12'));
    }

    private function thresholdIndicator(string $key, string $label, string $value, $favorable, $review, string $unit, string $explanation): array
    {
        $status = Money::lte($value, (string) $favorable) ? 'favorable' : (Money::lte($value, (string) $review) ? 'review' : 'unfavorable');
        $display = str_replace('.', ',', $value).$unit;

        return $this->indicator($key, $label, $display, "≤ {$favorable}{$unit} (análise ≤ {$review}{$unit})", $status, $explanation);
    }

    private function indicator(string $key, string $label, string $value, string $threshold, string $status, string $explanation): array
    {
        return compact('key', 'label', 'value', 'threshold', 'status', 'explanation');
    }

    private function pct(string $v): string
    {
        return str_replace('.', ',', $v).'%';
    }

    private function finish(array $indicators, array $in, string $monthlyInstallment, string $income): array
    {
        $statuses = array_column($indicators, 'status');
        $result = in_array('unfavorable', $statuses, true) ? 'unfavorable' : (in_array('review', $statuses, true) ? 'review' : 'favorable');

        $counts = array_count_values($statuses);
        $explanation = match ($result) {
            'favorable' => 'Todos os indicadores avaliados são favoráveis.',
            'review' => sprintf('%d indicador(es) exigem análise adicional; nenhum desfavorável.', $counts['review'] ?? 0),
            'unfavorable' => sprintf('%d indicador(es) desfavorável(is): %s.', $counts['unfavorable'] ?? 0,
                implode(', ', array_column(array_filter($indicators, fn ($i) => $i['status'] === 'unfavorable'), 'label'))),
        };
        $explanation .= ' Recomendação automática de apoio à decisão; não substitui a decisão do gestor autorizado.';

        $inputs = $in;
        $inputs['frequency'] = $in['frequency']->value;
        $inputs['monthly_installment_equivalent'] = $monthlyInstallment;
        $inputs['total_monthly_income'] = Money::toDb($income);

        return compact('result', 'indicators', 'explanation', 'inputs');
    }
}

<?php

namespace App\Services;

use App\Models\FinancialTransaction;
use App\Models\Loan;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Lançamentos no razão do empréstimo. Todos os movimentos são imutáveis.
 */
class LedgerService
{
    public function __construct(private readonly NumberGenerator $numbers) {}

    public function post(Loan $loan, string $type, string $component, string $direction, string $amount, ?Model $source = null, ?string $description = null, $postedAt = null): ?FinancialTransaction
    {
        $amount = Money::toDb($amount);
        if (Money::isZero($amount)) {
            return null;
        }

        return FinancialTransaction::create([
            'transaction_number' => $this->numbers->next('transaction'),
            'loan_id' => $loan->id,
            'client_id' => $loan->client_id,
            'type' => $type,
            'component' => $component,
            'direction' => $direction,
            'amount' => $amount,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'description' => $description,
            'posted_at' => $postedAt ?? now(),
            'created_by' => Auth::id(),
        ]);
    }

    /** Lança os quatro componentes de uma imputação/estorno. */
    public function postComponents(Loan $loan, string $type, string $direction, array $parts, ?Model $source, string $description, $postedAt = null): void
    {
        foreach (['principal', 'interest', 'fee', 'penalty'] as $component) {
            if (isset($parts[$component]) && Money::isPositive($parts[$component])) {
                $this->post($loan, $type, $component, $direction, $parts[$component], $source, $description, $postedAt);
            }
        }
    }

    /** Saldo por componente segundo o razão (débitos − créditos). */
    public function balances(Loan $loan): array
    {
        $rows = FinancialTransaction::where('loan_id', $loan->id)
            ->whereIn('component', ['principal', 'interest', 'fee', 'penalty'])
            ->selectRaw("component, SUM(CASE WHEN direction = 'debit' THEN amount ELSE 0 END) AS debits, SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END) AS credits")
            ->groupBy('component')
            ->get()
            ->keyBy('component');

        $out = [];
        foreach (['principal', 'interest', 'fee', 'penalty'] as $c) {
            $out[$c] = Money::toDb(Money::sub($rows[$c]->debits ?? '0', $rows[$c]->credits ?? '0'));
        }
        $out['total'] = Money::toDb(Money::add(...array_values($out)));

        return $out;
    }
}

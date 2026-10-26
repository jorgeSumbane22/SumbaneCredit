<?php

namespace App\Support;

use App\Models\LoanProduct;
use Illuminate\Support\Collection;

/** Dados dos produtos para os simuladores (JSON embebido no formulário). */
final class ProductCatalog
{
    public static function simulatorData(Collection $products): array
    {
        return $products->mapWithKeys(function (LoanProduct $p) {
            $v = $p->currentVersion;

            return [$p->id => [
                'name' => $p->name,
                'min_amount' => (string) $v->min_amount,
                'max_amount' => (string) $v->max_amount,
                'min_amount_label' => Money::format($v->min_amount),
                'max_amount_label' => Money::format($v->max_amount),
                'min_term' => $v->min_term,
                'max_term' => $v->max_term,
                'rate' => $v->rateLabel(),
                'frequencies' => collect($v->frequencies())->mapWithKeys(fn ($f) => [$f->value => $f->label()])->all(),
                'methods' => collect($v->methods())->mapWithKeys(fn ($m) => [$m->value => $m->shortLabel()])->all(),
            ]];
        })->all();
    }
}

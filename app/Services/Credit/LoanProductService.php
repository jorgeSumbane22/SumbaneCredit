<?php

namespace App\Services\Credit;

use App\Models\LoanProduct;
use App\Models\LoanProductVersion;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gestão de produtos. As condições financeiras são versionadas: qualquer alteração cria
 * uma nova versão; pedidos e contratos existentes mantêm a versão com que foram criados.
 */
class LoanProductService
{
    public const PRODUCT_FIELDS = ['code', 'name', 'description', 'requirements', 'contract_conditions', 'status', 'is_public'];

    public const VERSION_FIELDS = [
        'min_amount', 'max_amount', 'min_term', 'max_term', 'interest_rate', 'rate_period', 'allowed_frequencies',
        'amortization_methods', 'fees', 'penalty_type', 'penalty_value', 'penalty_grace_days', 'early_settlement_rule', 'eligibility_rules',
    ];

    public function __construct(private readonly AuditService $audit) {}

    public function create(array $data, User $user): LoanProduct
    {
        return DB::transaction(function () use ($data, $user) {
            $product = LoanProduct::create(Arr::only($data, self::PRODUCT_FIELDS) + [
                'slug' => $this->uniqueSlug($data['name']),
                'created_by' => $user->id,
            ]);
            $version = $this->createVersion($product, $data, $user, 'Versão inicial.');
            $product->update(['current_version_id' => $version->id]);

            return $product;
        });
    }

    /** @return array{product: LoanProduct, new_version: bool} */
    public function update(LoanProduct $product, array $data, User $user): array
    {
        return DB::transaction(function () use ($product, $data, $user) {
            $product = LoanProduct::lockForUpdate()->findOrFail($product->id);
            $product->fill(Arr::only($data, self::PRODUCT_FIELDS));
            if ($product->isDirty('name')) {
                $product->slug = $this->uniqueSlug($data['name'], $product->id);
            }
            $product->save();

            $current = $product->currentVersion;
            $newVersion = ! $current || $this->conditionsChanged($current, $data);
            if ($newVersion) {
                $version = $this->createVersion($product, $data, $user, $data['change_notes'] ?? null);
                $product->update(['current_version_id' => $version->id]);
            }

            return ['product' => $product, 'new_version' => $newVersion];
        });
    }

    private function createVersion(LoanProduct $product, array $data, User $user, ?string $notes): LoanProductVersion
    {
        $next = (int) LoanProductVersion::where('loan_product_id', $product->id)->max('version') + 1;
        $version = LoanProductVersion::create($this->normalize($data) + [
            'loan_product_id' => $product->id,
            'version' => $next,
            'change_notes' => $notes,
            'effective_from' => now(),
            'created_by' => $user->id,
        ]);
        $this->audit->log('product.version_created', $version, null, ['product' => $product->code, 'version' => $next] + $this->normalize($data));

        return $version;
    }

    private function normalize(array $data): array
    {
        $fees = collect($data['fees'] ?? [])->filter(fn ($f) => ! empty($f['name']) && isset($f['value']) && $f['value'] !== '')
            ->map(fn ($f) => [
                'name' => trim($f['name']),
                'type' => $f['type'] === 'percent' ? 'percent' : 'fixed',
                'value' => Money::round($f['value'], 4),
                'charge' => $f['charge'] === 'financed' ? 'financed' : 'upfront',
            ])->values()->all();

        $eligibility = array_filter([
            'min_monthly_income' => isset($data['eligibility']['min_monthly_income']) && $data['eligibility']['min_monthly_income'] !== '' ? Money::toDb($data['eligibility']['min_monthly_income']) : null,
            'max_active_loans' => isset($data['eligibility']['max_active_loans']) && $data['eligibility']['max_active_loans'] !== '' ? (int) $data['eligibility']['max_active_loans'] : null,
            'min_age' => (int) ($data['eligibility']['min_age'] ?? 18) ?: 18,
            'requires_guarantor' => ! empty($data['eligibility']['requires_guarantor']),
            'requires_collateral' => ! empty($data['eligibility']['requires_collateral']),
        ], fn ($v) => $v !== null);

        return [
            'min_amount' => Money::toDb($data['min_amount']),
            'max_amount' => Money::toDb($data['max_amount']),
            'min_term' => (int) $data['min_term'],
            'max_term' => (int) $data['max_term'],
            'interest_rate' => Money::round($data['interest_rate'], 4),
            'rate_period' => $data['rate_period'],
            'allowed_frequencies' => array_values(array_unique($data['allowed_frequencies'])),
            'amortization_methods' => array_values(array_unique($data['amortization_methods'])),
            'fees' => $fees,
            'penalty_type' => $data['penalty_type'],
            'penalty_value' => $data['penalty_type'] === 'none' ? '0.0000' : Money::round($data['penalty_value'] ?? '0', 4),
            'penalty_grace_days' => (int) ($data['penalty_grace_days'] ?? 0),
            'early_settlement_rule' => $data['early_settlement_rule'],
            'eligibility_rules' => $eligibility,
        ];
    }

    private function conditionsChanged(LoanProductVersion $current, array $data): bool
    {
        $new = $this->normalize($data);
        foreach ($new as $key => $value) {
            $old = $current->{$key};
            if (is_array($value) || is_array($old)) {
                if (json_encode($value) !== json_encode($old)) {
                    return true;
                }
            } elseif (is_numeric($value) && is_numeric((string) $old)) {
                if (! Money::eq((string) $value, (string) $old)) {
                    return true;
                }
            } elseif ((string) $value !== (string) $old) {
                return true;
            }
        }

        return false;
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'produto';
        $slug = $base;
        $i = 2;
        while (LoanProduct::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}

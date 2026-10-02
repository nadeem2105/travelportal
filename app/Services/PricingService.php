<?php

namespace App\Services;

use App\Models\PricingRule;
use App\Models\Supplier;
use App\Models\Tax;
use Illuminate\Support\Collection;

/**
 * Server-side price calculator. Frontend prices are NEVER trusted;
 * every booking recalculates its final price through this service.
 */
class PricingService
{
    public function __construct(protected SettingsService $settings)
    {
    }

    /**
     * Calculate the full price stack for a product.
     *
     * @param  float  $supplierCost  Base/supplier cost of the product
     * @param  string  $productType  flight|hotel|cab|package
     * @param  int|null  $supplierId  Supplier for supplier-specific rules
     */
    public function calculate(float $supplierCost, string $productType, ?int $supplierId = null, ?float $overrideMarkup = null): array
    {
        $rules = PricingRule::query()
            ->active()
            ->where(fn ($q) => $q->where('product_type', $productType)->orWhere('product_type', 'all'))
            ->where(fn ($q) => $q->whereNull('supplier_id')->orWhere('supplier_id', $supplierId))
            ->get();

        $supplierMarkupPercent = 0;
        $supplierServiceFee = 0;
        $supplierCommissionPercent = 0;

        if ($supplierId && $supplier = Supplier::find($supplierId)) {
            $supplierMarkupPercent = (float) $supplier->default_markup_percent;
            $supplierServiceFee = (float) $supplier->default_service_fee;
            $supplierCommissionPercent = (float) $supplier->default_commission_percent;
        }

        $markup = $overrideMarkup ?? $this->applyRules($rules, 'markup', $supplierCost, $supplierMarkupPercent);
        $serviceFee = $this->applyRules($rules, 'service_fee', $supplierCost, 0) + $supplierServiceFee;
        $convenienceFee = $this->applyRules($rules, 'convenience_fee', $supplierCost, 0);
        $commission = $this->applyRules($rules, 'commission', $supplierCost, $supplierCommissionPercent);

        $subtotal = $supplierCost + $markup;

        [$taxAmount, $taxes] = $this->calculateTaxes($subtotal + $serviceFee + $convenienceFee, $productType);

        $total = round($subtotal + $serviceFee + $convenienceFee + $taxAmount, 2);

        return [
            'supplier_cost' => round($supplierCost, 2),
            'markup_amount' => round($markup, 2),
            'service_fee' => round($serviceFee, 2),
            'convenience_fee' => round($convenienceFee, 2),
            'tax_amount' => round($taxAmount, 2),
            'taxes' => $taxes,
            'commission_amount' => round($commission, 2),
            'subtotal' => round($subtotal, 2),
            'total' => $total,
            'currency' => $this->settings->get('currency_code', 'INR'),
        ];
    }

    protected function applyRules(Collection $rules, string $ruleType, float $amount, float $supplierDefault = 0): float
    {
        $total = 0;
        $found = false;

        foreach ($rules->where('rule_type', $ruleType) as $rule) {
            if ($rule->min_amount !== null && $amount < $rule->min_amount) {
                continue;
            }
            if ($rule->max_amount !== null && $amount > $rule->max_amount) {
                continue;
            }
            $found = true;
            $total += $rule->calculation === 'percentage'
                ? $amount * ((float) $rule->value / 100)
                : (float) $rule->value;
        }

        if (! $found && $supplierDefault > 0) {
            $total = $amount * ($supplierDefault / 100);
        }

        return round($total, 2);
    }

    protected function calculateTaxes(float $amount, string $productType): array
    {
        $taxes = Tax::query()->forProduct($productType)->where('is_inclusive', false)->get();
        $total = 0;
        $breakdown = [];

        foreach ($taxes as $tax) {
            $value = $tax->calculation === 'percentage'
                ? $amount * ((float) $tax->value / 100)
                : (float) $tax->value;

            $total += $value;
            $breakdown[] = [
                'name' => $tax->name,
                'value' => round($value, 2),
            ];
        }

        return [round($total, 2), $breakdown];
    }
}

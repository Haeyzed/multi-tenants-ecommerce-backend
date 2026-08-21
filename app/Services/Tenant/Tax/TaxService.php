<?php

declare(strict_types=1);

namespace App\Services\Tenant\Tax;

use App\Enums\Tenant\Tax\TaxAppliesTo;
use App\Models\Tenant\Tax;
use App\Models\Tenant\TaxRate;
use App\Models\Tenant\TaxRule;
use App\Models\Tenant\TaxZone;
use App\Services\Tenant\Commerce\CommerceSettingService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Calculates line, shipping, and order tax with zone rules or flat-rate fallback.
 */
class TaxService
{
    /**
     * Create a new class instance.
     *
     * @param  CommerceSettingService  $commerceSettings
     */
    public function __construct(
        private readonly CommerceSettingService $commerceSettings,
    ) {}

    /**
     * Calculate tax for a single line amount.
     *
     * @param  string  $amount
     * @param  array{country_id?: int|null, state_id?: int|null, city_id?: int|null}  $address
     * @return array{tax_amount: string, breakdown: list<array<string, mixed>>}
     */
    public function calculateLineTax(string $amount, array $address = []): array
    {
        return $this->calculateForScope($amount, $address, TaxAppliesTo::Product);
    }

    /**
     * Calculate tax for a shipping amount.
     *
     * @param  string  $amount
     * @param  array{country_id?: int|null, state_id?: int|null, city_id?: int|null}  $address
     * @return array{tax_amount: string, breakdown: list<array<string, mixed>>}
     */
    public function calculateShippingTax(string $amount, array $address = []): array
    {
        if (bccomp($amount, '0', 2) <= 0) {
            return ['tax_amount' => '0.00', 'breakdown' => []];
        }

        return $this->calculateForScope($amount, $address, TaxAppliesTo::Shipping);
    }

    /**
     * Calculate order-level tax for cart/checkout lines and shipping.
     *
     * @param  list<array{key?: string|int, amount: string}>  $lines
     * @param  string  $shippingAmount
     * @param  list<array{key?: string|int, amount: string}>  $lines
     * @param  array{country_id?: int|null, state_id?: int|null, city_id?: int|null}  $address
     * @return array{
     */
    public function calculateOrderTax(array $lines, string $shippingAmount, array $address = []): array
    {
        $rules = $this->resolveApplicableRules($address);

        if ($rules->isEmpty()) {
            return $this->calculateWithFallback($lines, $shippingAmount);
        }

        $lineTaxes = [];
        $productTaxTotal = '0.00';
        $allBreakdown = [];

        foreach ($lines as $index => $line) {
            $key = $line['key'] ?? $index;
            $result = $this->calculateForScope((string) $line['amount'], $address, TaxAppliesTo::Product, $rules);
            $lineTaxes[] = [
                'key' => $key,
                'tax_amount' => $result['tax_amount'],
                'breakdown' => $result['breakdown'],
            ];
            $productTaxTotal = Money::add($productTaxTotal, $result['tax_amount']);
            $allBreakdown = array_merge($allBreakdown, $result['breakdown']);
        }

        $shippingResult = $this->calculateForScope($shippingAmount, $address, TaxAppliesTo::Shipping, $rules);
        $taxTotal = Money::add($productTaxTotal, $shippingResult['tax_amount']);
        $allBreakdown = array_merge($allBreakdown, $shippingResult['breakdown']);

        return [
            'tax_total' => $taxTotal,
            'shipping_tax' => $shippingResult['tax_amount'],
            'line_taxes' => $lineTaxes,
            'snapshot' => [
                'mode' => 'engine',
                'address' => $address,
                'breakdown' => $allBreakdown,
                'product_tax_total' => $productTaxTotal,
                'shipping_tax' => $shippingResult['tax_amount'],
            ],
            'uses_fallback' => false,
        ];
    }

    /**
     * Calculate for scope.
     *
     * @param  string  $amount
     * @param  array{country_id?: int|null, state_id?: int|null, city_id?: int|null}  $address
     * @param  TaxAppliesTo  $scope
     * @param  ?Collection  $rules
     * @return array{tax_amount: string, breakdown: list<array<string, mixed>>}
     */
    protected function calculateForScope(
        string $amount,
        array $address,
        TaxAppliesTo $scope,
        ?Collection $rules = null,
    ): array {
        if (bccomp($amount, '0', 2) <= 0) {
            return ['tax_amount' => '0.00', 'breakdown' => []];
        }

        $rules ??= $this->resolveApplicableRules($address);

        $scoped = $rules->filter(function (TaxRule $rule) use ($scope): bool {
            return $rule->applies_to === TaxAppliesTo::All || $rule->applies_to === $scope;
        });

        if ($scoped->isEmpty()) {
            return ['tax_amount' => '0.00', 'breakdown' => []];
        }

        $taxAmount = '0.00';
        $breakdown = [];

        foreach ($scoped as $rule) {
            /** @var Tax $tax */
            $tax = $rule->tax;
            $rate = $this->currentRate($tax);

            if ($rate === null || bccomp((string) $rate->rate, '0', 4) <= 0) {
                continue;
            }

            $lineTax = $this->taxFromAmount($amount, (string) $rate->rate, (bool) $tax->is_inclusive);
            $taxAmount = Money::add($taxAmount, $lineTax);

            $breakdown[] = [
                'tax_id' => $tax->id,
                'tax_code' => $tax->code,
                'tax_name' => $tax->name,
                'rate' => (string) $rate->rate,
                'is_inclusive' => (bool) $tax->is_inclusive,
                'applies_to' => $rule->applies_to->value,
                'tax_amount' => $lineTax,
                'base_amount' => $amount,
            ];
        }

        return [
            'tax_amount' => $taxAmount,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * tax_total: string, shipping_tax: string, line_taxes: list<array{key: string|int, tax_amount: string, breakdown: list<array<string, mixed>>}>, snapshot: array<string, mixed>, uses_fallback: bool }
     *
     * @param  list<array{key?: string|int, amount: string}>  $lines
     * @param  string  $shippingAmount
     * @return array{
     */
    protected function calculateWithFallback(array $lines, string $shippingAmount): array
    {
        $rate = $this->commerceSettings->taxRate();
        $subtotal = '0.00';

        foreach ($lines as $line) {
            $subtotal = Money::add($subtotal, (string) $line['amount']);
        }

        $taxTotal = Money::percent($subtotal, $rate);
        $lineTaxes = [];

        foreach ($lines as $index => $line) {
            $key = $line['key'] ?? $index;
            $lineTax = Money::percent((string) $line['amount'], $rate);
            $lineTaxes[] = [
                'key' => $key,
                'tax_amount' => $lineTax,
                'breakdown' => [[
                    'mode' => 'fallback',
                    'rate' => $rate,
                    'tax_amount' => $lineTax,
                ]],
            ];
        }

        return [
            'tax_total' => $taxTotal,
            'shipping_tax' => '0.00',
            'line_taxes' => $lineTaxes,
            'snapshot' => [
                'mode' => 'fallback',
                'rate' => $rate,
                'subtotal' => $subtotal,
                'shipping_amount' => $shippingAmount,
            ],
            'uses_fallback' => true,
        ];
    }

    /**
     * Resolve applicable rules.
     *
     * @param  array{country_id?: int|null, state_id?: int|null, city_id?: int|null}  $address
     * @return Collection<int, TaxRule>
     */
    protected function resolveApplicableRules(array $address): Collection
    {
        $zones = $this->matchingZones($address);

        if ($zones->isEmpty()) {
            return collect();
        }

        return TaxRule::query()
            ->with(['tax.rates'])
            ->where('is_active', true)
            ->whereIn('tax_zone_id', $zones->pluck('id'))
            ->whereHas('tax', fn ($query) => $query->where('is_active', true))
            ->get()
            ->sortBy(fn (TaxRule $rule): int => (int) ($rule->tax?->priority ?? 0))
            ->values();
    }

    /**
     * Matching zones.
     *
     * @param  array{country_id?: int|null, state_id?: int|null, city_id?: int|null}  $address
     * @return Collection<int, TaxZone>
     */
    protected function matchingZones(array $address): Collection
    {
        $countryId = isset($address['country_id']) ? (int) $address['country_id'] : null;
        $stateId = isset($address['state_id']) ? (int) $address['state_id'] : null;
        $cityId = isset($address['city_id']) ? (int) $address['city_id'] : null;

        $zones = TaxZone::query()
            ->with('locations')
            ->where('is_active', true)
            ->get();

        return $zones
            ->filter(function (TaxZone $zone) use ($countryId, $stateId, $cityId): bool {
                foreach ($zone->locations as $location) {
                    if ($this->locationMatches($location->country_id, $countryId)
                        && $this->locationMatches($location->state_id, $stateId)
                        && $this->locationMatches($location->city_id, $cityId)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    /**
     * Location matches.
     *
     * @param  ?int  $ruleValue
     * @param  ?int  $addressValue
     * @return bool
     */
    protected function locationMatches(?int $ruleValue, ?int $addressValue): bool
    {
        if ($ruleValue === null) {
            return true;
        }

        return $addressValue !== null && $ruleValue === $addressValue;
    }

    /**
     * Current rate.
     *
     * @param  Tax  $tax
     * @return ?TaxRate
     */
    protected function currentRate(Tax $tax): ?TaxRate
    {
        $today = Carbon::today();

        return $tax->rates
            ->filter(function (TaxRate $rate) use ($today): bool {
                if ($rate->effective_from !== null && $rate->effective_from->gt($today)) {
                    return false;
                }

                if ($rate->effective_to !== null && $rate->effective_to->lt($today)) {
                    return false;
                }

                return true;
            })
            ->sortByDesc('effective_from')
            ->first();
    }

    /**
     * Tax from amount.
     *
     * @param  string  $amount
     * @param  string  $rate
     * @param  bool  $inclusive
     * @return string
     */
    protected function taxFromAmount(string $amount, string $rate, bool $inclusive): string
    {
        if ($inclusive) {
            $divisor = bcadd('1', bcdiv($rate, '100', 6), 6);

            return bcsub($amount, bcdiv($amount, $divisor, 4), 2);
        }

        return Money::percent($amount, $rate);
    }
}

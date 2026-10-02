<?php

namespace App\Services;

use App\Models\FlightSearch;
use App\Models\Supplier;
use App\Services\Suppliers\FlightSupplierInterface;
use App\Services\Suppliers\SupplierAdapterRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Flight engine: fans a search out to all enabled suppliers by priority,
 * merges results, applies pricing, and caches per search signature.
 * Controllers never talk to suppliers directly.
 */
class FlightEngine
{
    public function __construct(
        protected PricingService $pricing,
        protected SettingsService $settings,
    ) {
    }

    public function search(array $params): array
    {
        $signature = 'flight_search:' . md5(json_encode($params));

        return Cache::remember($signature, 300, function () use ($params, $signature) {
            $allResults = [];
            $errors = [];

            foreach ($this->activeSuppliers() as $supplier) {
                try {
                    $adapter = $this->adapter($supplier);
                    $response = $adapter->searchFlights($params);

                    if ($response['success'] ?? false) {
                        foreach ($response['results'] as $result) {
                            $allResults[] = $this->applyPricing($result, $supplier);
                        }
                    } else {
                        $errors[] = $response['error'] ?? 'Supplier did not return results.';
                    }
                } catch (\Throwable $e) {
                    Log::error('Flight supplier search failed: ' . $supplier->name, [
                        'error' => $e->getMessage(),
                    ]);
                    $errors[] = 'supplier_error';
                }
            }

            usort($allResults, fn ($a, $b) => $a['fare']['display_total'] <=> $b['fare']['display_total']);

            FlightSearch::create([
                'user_id' => auth('web')->id(),
                'params' => $params,
                'results_count' => count($allResults),
            ]);

            return [
                'results' => $allResults,
                'count' => count($allResults),
                'has_supplier_errors' => count($errors) > 0 && count($allResults) === 0,
                'signature' => $signature,
            ];
        });
    }

    public function applyPricing(array $result, ?Supplier $supplier = null): array
    {
        $price = $this->pricing->calculate(
            (float) $result['supplier_cost'],
            'flight',
            $supplier?->id ?? $result['supplier_id'] ?? null
        );

        $travellerFactor = $this->travellerFactor();

        $baseFare = ! empty($result['fare']['base'])
            ? round((float) $result['fare']['base'] + $price['markup_amount'], 2)
            : $price['subtotal'];
        $taxesAndFees = round($price['total'] - $baseFare, 2);

        $result['fare']['base'] = $baseFare;
        $result['fare']['taxes_and_fees'] = $taxesAndFees;
        $result['fare']['display_total'] = $price['total'];
        $result['fare']['per_traveller'] = $price['total'];
        $result['fare']['total_for_all'] = round($price['total'] * $travellerFactor, 2);
        $result['pricing'] = $price;

        return $result;
    }

    protected function travellerFactor(): int
    {
        $session = session('flight_search_params', []);

        $adults = (int) ($session['adults'] ?? 1);
        $children = (int) ($session['children'] ?? 0);
        $infants = (int) ($session['infants'] ?? 0);

        // infants travel on lap at nominal fare handled at booking time
        return max(1, $adults + $children);
    }

    public function getByResultId(string $resultId, array $params): ?array
    {
        $search = $this->search($params);

        foreach ($search['results'] as $result) {
            if ($result['result_id'] === $resultId) {
                return $result;
            }
        }

        return null;
    }

    public function adapter(Supplier $supplier): FlightSupplierInterface
    {
        return SupplierAdapterRegistry::make($supplier);
    }

    public function activeSuppliers()
    {
        $suppliers = Supplier::forType('flight')->get();

        if ($suppliers->isEmpty()) {
            $fallback = Supplier::firstOrCreate(
                ['slug' => 'demo-flights'],
                [
                    'name' => 'Demo Flight API',
                    'type' => 'flight',
                    'driver' => 'demo',
                    'is_active' => true,
                    'priority' => 1,
                ]
            );

            return collect([$fallback]);
        }

        return $suppliers;
    }

    public function defaultSupplier(): ?Supplier
    {
        return $this->activeSuppliers()->first();
    }
}

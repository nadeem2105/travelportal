<?php

namespace App\Services;

use App\Models\Supplier;
use App\Services\Suppliers\HotelSupplierInterface;
use App\Services\Suppliers\SupplierAdapterRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HotelEngine
{
    public function __construct(protected PricingService $pricing)
    {
    }

    public function search(array $params): array
    {
        $signature = 'hotel_search:' . md5(json_encode($params));

        return Cache::remember($signature, 120, function () use ($params, $signature) {
            $deduplicated = [];

            foreach ($this->activeSuppliers() as $supplier) {
                try {
                    $adapter = $this->adapter($supplier);
                    $response = $adapter->searchHotels($params);

                    if ($response['success'] ?? false) {
                        foreach ($response['results'] as $result) {
                            if ($result['raw_starting_price'] ?? $result['starting_price'] ?? false) {
                                $result = $this->applyPricing($result, $supplier);
                            }

                            $key = $this->generateHotelKey($result);

                            if (! isset($deduplicated[$key])) {
                                $result['supplier_offers'] = [
                                    [
                                        'supplier_id' => $supplier->id,
                                        'supplier_name' => $supplier->name,
                                        'price' => $result['display_price'] ?? $result['starting_price'],
                                    ],
                                ];
                                $deduplicated[$key] = $result;
                            } else {
                                // Add supplier offer to existing hotel
                                $deduplicated[$key]['supplier_offers'][] = [
                                    'supplier_id' => $supplier->id,
                                    'supplier_name' => $supplier->name,
                                    'price' => $result['display_price'] ?? $result['starting_price'],
                                ];

                                // If this supplier offers a cheaper rate, update display price
                                $existingPrice = $deduplicated[$key]['display_price'] ?? $deduplicated[$key]['starting_price'] ?? 999999;
                                $newPrice = $result['display_price'] ?? $result['starting_price'] ?? 999999;

                                if ($newPrice < $existingPrice) {
                                    $deduplicated[$key]['starting_price'] = $result['starting_price'];
                                    $deduplicated[$key]['display_price'] = $result['display_price'];
                                    $deduplicated[$key]['supplier_id'] = $supplier->id;
                                    $deduplicated[$key]['supplier_name'] = $supplier->name;
                                    $deduplicated[$key]['pricing'] = $result['pricing'] ?? $deduplicated[$key]['pricing'];
                                }

                                // Merge amenities and photos if missing
                                if (empty($deduplicated[$key]['photos']) && ! empty($result['photos'])) {
                                    $deduplicated[$key]['photos'] = $result['photos'];
                                }
                                if (empty($deduplicated[$key]['amenities']) && ! empty($result['amenities'])) {
                                    $deduplicated[$key]['amenities'] = $result['amenities'];
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('Hotel supplier search failed: ' . $supplier->name, [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $allResults = array_values($deduplicated);

            usort($allResults, fn ($a, $b) => ($a['display_price'] ?? $a['starting_price'] ?? 999999)
                <=> ($b['display_price'] ?? $b['starting_price'] ?? 999999));

            return [
                'results' => $allResults,
                'count' => count($allResults),
                'signature' => $signature,
            ];
        });
    }

    public function applyPricing(array $result, ?Supplier $supplier = null): array
    {
        $cost = (float) ($result['raw_starting_price'] ?? $result['starting_price'] ?? 0);
        $price = $this->pricing->calculate($cost, 'hotel', $supplier?->id ?? $result['supplier_id'] ?? null);

        $result['display_price'] = $price['total'];
        $result['pricing'] = $price;

        return $result;
    }

    public function getRooms(string $hotelCode, array $params, ?int $supplierId = null): array
    {
        $supplier = $supplierId ? Supplier::find($supplierId) : $this->resolveSupplier($hotelCode);

        if (! $supplier) {
            return ['success' => false, 'error' => 'Supplier not available.'];
        }

        $response = $this->adapter($supplier)->getRooms($hotelCode, $params);

        if ($response['success'] ?? false) {
            $response['rooms'] = array_map(function ($room) use ($supplier) {
                $price = $this->pricing->calculate((float) $room['supplier_cost'], 'hotel', $supplier->id);
                $room['display_price'] = $price['total'];
                $room['pricing'] = $price;

                return $room;
            }, $response['rooms']);
        }

        return $response;
    }

    public function adapter(Supplier $supplier): HotelSupplierInterface
    {
        return SupplierAdapterRegistry::make($supplier);
    }

    public function activeSuppliers()
    {
        return Supplier::forType('hotel')->get();
    }

    protected function resolveSupplier(string $hotelCode): ?Supplier
    {
        if (str_starts_with($hotelCode, 'MANUAL-')) {
            return Supplier::where('slug', 'manual-hotels')->first() ?? $this->activeSuppliers()->first();
        }

        return $this->activeSuppliers()->first();
    }

    protected function generateHotelKey(array $result): string
    {
        if (! empty($result['slug'])) {
            return 'slug:' . $result['slug'];
        }

        $city = strtolower(trim($result['city'] ?? ''));
        $name = strtolower(preg_replace('/[^a-z0-9]/', '', str_ireplace(['hotel', 'resort', 'the', 'inn', 'palace', 'houseboat'], '', $result['name'] ?? '')));

        return 'key:' . $city . ':' . $name;
    }
}

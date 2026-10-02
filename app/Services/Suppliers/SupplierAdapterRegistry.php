<?php

namespace App\Services\Suppliers;

use App\Models\Supplier;
use InvalidArgumentException;

/**
 * Maps adapter keys to adapter classes. Adding a new supplier API later
 * means writing one adapter class and registering it here — no controller
 * changes required.
 */
class SupplierAdapterRegistry
{
    public const FLIGHT_ADAPTERS = [
        'demo' => \App\Services\Suppliers\Flights\DemoFlightSupplier::class,
        'amadeus' => \App\Services\Suppliers\Flights\AmadeusFlightSupplier::class,
        'tbo' => \App\Services\Suppliers\Flights\TBOFlightSupplier::class,
        'akbar' => \App\Services\Suppliers\Flights\AkbarFlightSupplier::class,
    ];

    public const HOTEL_ADAPTERS = [
        'manual' => \App\Services\Suppliers\Hotels\ManualHotelSupplier::class,
        'demo' => \App\Services\Suppliers\Hotels\DemoHotelSupplier::class,
    ];

    public static function make(Supplier $supplier): object
    {
        $map = $supplier->type === 'flight' ? self::FLIGHT_ADAPTERS : self::HOTEL_ADAPTERS;

        $adapter = $supplier->adapter ?? 'demo';

        if (! isset($map[$adapter])) {
            throw new InvalidArgumentException("Unknown [{$adapter}] adapter for supplier [{$supplier->name}].");
        }

        $class = $map[$adapter];

        $credentials = [];
        $credModel = $supplier->credentialFor($supplier->environment);
        if ($credModel && $credModel->is_active) {
            $credentials = $credModel->credentials ?? [];
        }

        return new $class($supplier, $credentials);
    }
}

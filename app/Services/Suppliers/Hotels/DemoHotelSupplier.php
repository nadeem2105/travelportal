<?php

namespace App\Services\Suppliers\Hotels;

use App\Models\Supplier;
use App\Services\Suppliers\HotelSupplierInterface;

/**
 * Demo aggregator-style supplier skeleton for API-connected hotel sources.
 * Returns an empty successful response until configured with live keys.
 */
class DemoHotelSupplier implements HotelSupplierInterface
{
    public function __construct(protected Supplier $supplier, protected array $credentials = [])
    {
    }

    public function searchHotels(array $params): array
    {
        return ['success' => true, 'results' => [], 'cache_ttl' => 120];
    }

    public function getHotelDetails(string $hotelCode, array $params): array
    {
        return ['success' => false, 'error' => 'Hotel supplier is not configured yet.'];
    }

    public function getRooms(string $hotelCode, array $params): array
    {
        return ['success' => false, 'error' => 'Hotel supplier is not configured yet.'];
    }

    public function checkAvailability(string $hotelCode, string $roomCode, array $params): array
    {
        return ['success' => false, 'error' => 'Hotel supplier is not configured yet.'];
    }

    public function createBooking(string $hotelCode, string $roomCode, array $params, array $guests, array $contact): array
    {
        return ['success' => false, 'error' => 'Hotel supplier is not configured yet.'];
    }

    public function cancelBooking(string $supplierBookingId): array
    {
        return ['success' => false, 'error' => 'Not configured.'];
    }

    public function getBookingStatus(string $supplierBookingId): array
    {
        return ['success' => false, 'error' => 'Not configured.'];
    }

    public function getRefundStatus(string $supplierBookingId): array
    {
        return ['success' => false, 'error' => 'Not configured.'];
    }
}

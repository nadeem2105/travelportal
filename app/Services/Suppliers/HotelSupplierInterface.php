<?php

namespace App\Services\Suppliers;

use App\Models\Supplier;

/**
 * Contract every hotel supplier adapter must implement.
 */
interface HotelSupplierInterface
{
    public function __construct(Supplier $supplier, array $credentials);

    /**
     * Search hotels. Params: destination(city/destination_id), check_in,
     * check_out, adults, children, rooms, nationality, star_ratings.
     *
     * @return array{success: bool, results: array[], error?: string, cache_ttl?: int}
     */
    public function searchHotels(array $params): array;

    /**
     * Hotel details + rooms with availability for given dates.
     */
    public function getHotelDetails(string $hotelCode, array $params): array;

    public function getRooms(string $hotelCode, array $params): array;

    public function checkAvailability(string $hotelCode, string $roomCode, array $params): array;

    /**
     * @return array{success: bool, supplier_booking_id?: string, status?: string, error?: string}
     */
    public function createBooking(string $hotelCode, string $roomCode, array $params, array $guests, array $contact): array;

    public function cancelBooking(string $supplierBookingId): array;

    public function getBookingStatus(string $supplierBookingId): array;

    public function getRefundStatus(string $supplierBookingId): array;
}

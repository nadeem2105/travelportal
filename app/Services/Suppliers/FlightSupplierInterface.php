<?php

namespace App\Services\Suppliers;

use App\Models\Supplier;

/**
 * Contract every flight supplier adapter must implement.
 * All methods work with plain arrays (cache/serialization friendly).
 */
interface FlightSupplierInterface
{
    public function __construct(Supplier $supplier, array $credentials);

    /**
     * Search flights. Params: trip_type, from, to, departure, return,
     * adults, children, infants, cabin_class.
     *
     * @return array{success: bool, results: array[], error?: string, cache_ttl?: int}
     */
    public function searchFlights(array $params): array;

    /**
     * Details for one selected offer (by result_id from search results).
     */
    public function getFlightDetails(string $resultId, array $params): array;

    public function getFareRules(string $resultId, array $params): array;

    public function getSeatMap(string $resultId, array $params): array;

    public function getBaggage(string $resultId, array $params): array;

    /**
     * Create booking at the supplier. $travellers is an array of traveller data,
     * $contact is the booking contact array.
     *
     * @return array{success: bool, supplier_booking_id?: string, pnr?: string, status?: string, error?: string}
     */
    public function createBooking(string $resultId, array $params, array $travellers, array $contact): array;

    public function issueTicket(string $supplierBookingId): array;

    public function cancelBooking(string $supplierBookingId): array;

    public function getBookingStatus(string $supplierBookingId): array;

    public function getRefundStatus(string $supplierBookingId): array;
}

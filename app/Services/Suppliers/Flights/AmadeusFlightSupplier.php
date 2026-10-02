<?php

namespace App\Services\Suppliers\Flights;

use App\Models\Supplier;
use App\Services\Suppliers\FlightSupplierInterface;
use Illuminate\Support\Facades\Http;

/**
 * Amadeus Self-Service API adapter (skeleton).
 *
 * To go live: enter API credentials in Admin → Suppliers, enable this
 * supplier, and complete the endpoints below. The rest of the platform
 * (search, results, booking, payments) already talks to this adapter
 * through FlightSupplierInterface, so no other code changes.
 */
class AmadeusFlightSupplier implements FlightSupplierInterface
{
    public function __construct(protected Supplier $supplier, protected array $credentials = [])
    {
    }

    protected function baseUrl(): string
    {
        return $this->supplier->environment === 'production'
            ? 'https://api.amadeus.com'
            : 'https://test.api.amadeus.com';
    }

    protected function client()
    {
        return Http::baseUrl($this->baseUrl())
            ->timeout($this->supplier->timeout_seconds)
            ->retry($this->supplier->retry_attempts, 500);
    }

    protected function oauthToken(): ?string
    {
        $response = Http::asForm()->post($this->baseUrl() . '/v1/security/oauth2/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $this->credentials['client_id'] ?? '',
            'client_secret' => $this->credentials['client_secret'] ?? '',
        ]);

        return $response->successful() ? $response->json('access_token') : null;
    }

    public function searchFlights(array $params): array
    {
        return [
            'success' => false,
            'results' => [],
            'error' => 'Amadeus adapter is not configured yet. Add API credentials in Admin → Suppliers.',
        ];
    }

    public function getFlightDetails(string $resultId, array $params): array
    {
        return ['success' => false, 'error' => 'Not configured.'];
    }

    public function getFareRules(string $resultId, array $params): array
    {
        return ['success' => false, 'error' => 'Not configured.'];
    }

    public function getSeatMap(string $resultId, array $params): array
    {
        return ['success' => false, 'error' => 'Not configured.'];
    }

    public function getBaggage(string $resultId, array $params): array
    {
        return ['success' => false, 'error' => 'Not configured.'];
    }

    public function createBooking(string $resultId, array $params, array $travellers, array $contact): array
    {
        return ['success' => false, 'error' => 'Not configured.'];
    }

    public function issueTicket(string $supplierBookingId): array
    {
        return ['success' => false, 'error' => 'Not configured.'];
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

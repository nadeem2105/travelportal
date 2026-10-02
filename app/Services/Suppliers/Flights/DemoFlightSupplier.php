<?php

namespace App\Services\Suppliers\Flights;

use App\Models\Supplier;
use App\Services\Suppliers\FlightSupplierInterface;

/**
 * Deterministic demo supplier: generates realistic, stable flight results
 * for any city pair so the whole booking flow works without live API keys.
 * Results are seeded by a hash of the search params so they stay stable
 * while a search session is alive.
 */
class DemoFlightSupplier implements FlightSupplierInterface
{
    public function __construct(protected Supplier $supplier, protected array $credentials = [])
    {
    }

    protected const AIRLINES = [
        ['code' => 'UK', 'name' => 'Vistara', 'base' => 5600, 'onTime' => 88],
        ['code' => '6E', 'name' => 'IndiGo', 'base' => 4900, 'onTime' => 84],
        ['code' => 'AI', 'name' => 'Air India', 'base' => 5900, 'onTime' => 79],
        ['code' => 'QP', 'name' => 'Akasa Air', 'base' => 4600, 'onTime' => 81],
        ['code' => 'SG', 'name' => 'SpiceJet', 'base' => 4300, 'onTime' => 72],
        ['code' => 'IX', 'name' => 'Air India Express', 'base' => 4400, 'onTime' => 77],
    ];

    protected function seed(array $params): int
    {
        return crc32(implode('|', [
            $params['from'] ?? '', $params['to'] ?? '', $params['departure'] ?? '',
            $params['cabin_class'] ?? 'economy',
        ]));
    }

    public function searchFlights(array $params): array
    {
        $seed = $this->seed($params);
        mt_srand($seed);

        $results = [];

        foreach (self::AIRLINES as $i => $airline) {
            $count = 2 + ($seed + $i) % 2; // 2-3 options per airline

            for ($j = 0; $j < $count; $j++) {
                $results[] = $this->buildResult($airline, $i, $j, $params, $seed);
            }
        }

        usort($results, fn ($a, $b) => $a['fare']['total'] <=> $b['fare']['total']);

        return [
            'success' => true,
            'results' => $results,
            'cache_ttl' => 300, // 5 minutes
        ];
    }

    protected function buildResult(array $airline, int $i, int $j, array $params, int $seed): array
    {
        $from = $params['from'] ?? 'DEL';
        $to = $params['to'] ?? 'SXR';
        $departure = $params['departure'] ?? now()->addDays(7)->toDateString();

        $stops = ($seed + $i + $j) % 5 === 0 ? 1 : 0;
        $cabinFactor = match ($params['cabin_class'] ?? 'economy') {
            'premium_economy' => 1.35,
            'business' => 2.6,
            'first' => 4.0,
            default => 1.0,
        };

        $fare = round(($airline['base'] + $j * 850 + $i * 120) * $cabinFactor + ($stops ? -600 : 0));
        $depHour = 6 + (($seed + $i * 3 + $j * 5) % 15);
        $depMin = ($seed + $j * 17) % 2 === 0 ? '00' : '30';
        $durationMin = 90 + (($seed + $i) % 5) * 15 + $stops * 90;

        $flightNumber = $airline['code'] . '-' . (400 + (($seed + $i * 7 + $j * 13) % 590));
        $depTime = sprintf('%02d:%s', $depHour, $depMin);
        $arrTime = now()->parse($depTime)->addMinutes($durationMin)->format('H:i');

        $result = [
            'result_id' => 'DEMO-' . $seed . '-' . $i . '-' . $j,
            'supplier_id' => $this->supplier->id,
            'supplier_name' => $this->supplier->name,
            'supplier_cost' => $fare,
            'airline' => ['code' => $airline['code'], 'name' => $airline['name']],
            'flight_number' => $flightNumber,
            'cabin_class' => $params['cabin_class'] ?? 'economy',
            'stops' => $stops,
            'refundable' => ($seed + $i + $j) % 3 !== 0,
            'on_time_performance' => $airline['onTime'],
            'segments' => [],
            'fare' => [
                'base' => round($fare * 0.82),
                'taxes_and_fees' => round($fare * 0.18),
                'total' => $fare,
            ],
        ];

        $result['segments'] = $this->buildSegments($from, $to, $departure, $depTime, $arrTime, $durationMin, $stops, $airline, $flightNumber);

        if (($params['trip_type'] ?? 'one_way') === 'round_trip' && ! empty($params['return'])) {
            $result['return_segments'] = $this->buildSegments(
                $to, $from, $params['return'], '14:00', '16:30', 90,
                ($seed + $i) % 4 === 0 ? 1 : 0, $airline, $flightNumber
            );
        }

        return $result;
    }

    protected function buildSegments(string $from, string $to, string $date, string $depTime, string $arrTime, int $durationMin, int $stops, array $airline, string $flightNumber): array
    {
        $segment = [
            'airline' => $airline,
            'flight_number' => $flightNumber,
            'from' => ['code' => $from, 'city' => $this->cityFor($from), 'airport' => $this->airportFor($from), 'date' => $date, 'time' => $depTime],
            'to' => ['code' => $to, 'city' => $this->cityFor($to), 'airport' => $this->airportFor($to), 'date' => $date, 'time' => $arrTime],
            'duration_minutes' => $durationMin,
            'cabin_class' => 'economy',
            'baggage' => ['check_in' => $airline['code'] === '6E' ? '15 Kg' : '20 Kg', 'cabin' => '7 Kg'],
            'seat_remaining' => 3 + ($durationMin % 7),
        ];

        if ($stops > 0) {
            $segment['stopover'] = ['airport' => 'ATQ Amritsar', 'duration_minutes' => 85];
        }

        return [$segment];
    }

    protected function cityFor(string $code): string
    {
        return match ($code) {
            'DEL' => 'New Delhi', 'SXR' => 'Srinagar', 'BOM' => 'Mumbai', 'BLR' => 'Bengaluru',
            'HYD' => 'Hyderabad', 'CCU' => 'Kolkata', 'MAA' => 'Chennai', 'ATQ' => 'Amritsar',
            'JAI' => 'Jaipur', 'GOI' => 'Goa', 'IXC' => 'Chandigarh', 'PAT' => 'Patna',
            default => $code,
        };
    }

    protected function airportFor(string $code): string
    {
        return match ($code) {
            'DEL' => 'Indira Gandhi International Airport',
            'SXR' => 'Sheikh ul-Alam International Airport',
            'BOM' => 'Chhatrapati Shivaji Maharaj International Airport',
            'BLR' => 'Kempegowda International Airport',
            'HYD' => 'Rajiv Gandhi International Airport',
            'CCU' => 'Netaji Subhas Chandra Bose International Airport',
            'MAA' => 'Chennai International Airport',
            'ATQ' => 'Sri Guru Ram Dass Jee International Airport',
            default => $code . ' Airport',
        };
    }

    protected function findResult(string $resultId, array $params): ?array
    {
        $response = $this->searchFlights($params);

        foreach ($response['results'] ?? [] as $result) {
            if ($result['result_id'] === $resultId) {
                return $result;
            }
        }

        return null;
    }

    public function getFlightDetails(string $resultId, array $params): array
    {
        $result = $this->findResult($resultId, $params);

        return $result
            ? ['success' => true, 'flight' => $result]
            : ['success' => false, 'error' => 'The selected flight is no longer available.'];
    }

    public function getFareRules(string $resultId, array $params): array
    {
        $result = $this->findResult($resultId, $params);

        return [
            'success' => true,
            'rules' => [
                'cancellation' => $result && $result['refundable']
                    ? 'Cancellable up to 24 hours before departure. Cancellation fee of ₹3,000 or 10% of base fare (whichever is lower) applies.'
                    : 'Non-refundable. Airline penalty of ₹3,500 applies for date changes.',
                'date_change' => 'Date change permitted up to 4 hours before departure with a fee of ₹2,500 per passenger plus fare difference.',
                'seat' => 'Seats are assigned at check-in. Advance seat selection may carry an additional fee.',
            ],
        ];
    }

    public function getSeatMap(string $resultId, array $params): array
    {
        mt_srand(crc32($resultId));

        $rows = [];
        for ($row = 1; $row <= 20; $row++) {
            $seats = [];
            foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $col) {
                $seats[$col] = [
                    'available' => mt_rand(1, 10) > 3,
                    'window' => in_array($col, ['A', 'F']),
                    'price' => in_array($col, ['A', 'F']) ? 450 : (in_array($col, ['B', 'E']) ? 0 : 250),
                ];
            }
            $rows[$row] = $seats;
        }

        return ['success' => true, 'rows' => $rows];
    }

    public function getBaggage(string $resultId, array $params): array
    {
        return [
            'success' => true,
            'options' => [
                ['id' => 'bg-0', 'label' => 'No extra baggage', 'price' => 0],
                ['id' => 'bg-5', 'label' => '+5 Kg check-in baggage', 'price' => 1250],
                ['id' => 'bg-10', 'label' => '+10 Kg check-in baggage', 'price' => 2100],
                ['id' => 'bg-excess', 'label' => '+1 extra cabin bag (7 Kg)', 'price' => 900],
            ],
        ];
    }

    public function createBooking(string $resultId, array $params, array $travellers, array $contact): array
    {
        $result = $this->findResult($resultId, $params);

        if (! $result) {
            return ['success' => false, 'error' => 'Fare expired. Please search again.'];
        }

        return [
            'success' => true,
            'supplier_booking_id' => 'DEMO-FL-' . strtoupper(substr(md5($resultId . microtime()), 0, 10)),
            'pnr' => strtoupper(substr(md5($resultId . now()), 0, 6)),
            'status' => 'confirmed',
        ];
    }

    public function issueTicket(string $supplierBookingId): array
    {
        return [
            'success' => true,
            'status' => 'ticketed',
            'ticket_number' => '098-' . random_int(1000000000, 9999999999),
        ];
    }

    public function cancelBooking(string $supplierBookingId): array
    {
        return ['success' => true, 'status' => 'cancelled', 'refund_eta_days' => 7];
    }

    public function getBookingStatus(string $supplierBookingId): array
    {
        return ['success' => true, 'status' => 'confirmed'];
    }

    public function getRefundStatus(string $supplierBookingId): array
    {
        return ['success' => true, 'status' => 'processed', 'amount' => null];
    }
}

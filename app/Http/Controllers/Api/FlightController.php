<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FlightEngine;
use Illuminate\Http\Request;

class FlightController extends Controller
{
    public function __construct(protected FlightEngine $engine)
    {
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3|different:from',
            'departure' => 'required|date|after_or_equal:today',
            'return' => 'nullable|date|after:departure',
            'adults' => 'nullable|integer|min:1|max:9',
            'children' => 'nullable|integer|min:0|max:9',
            'cabin_class' => 'nullable|in:economy,premium_economy,business,first',
        ]);

        $response = $this->engine->search($validated);

        return response()->json([
            'data' => collect($response['results'])->map(fn ($r) => [
                'result_id' => $r['result_id'],
                'airline' => $r['airline'],
                'flight_number' => $r['flight_number'],
                'segments' => $r['segments'],
                'stops' => $r['stops'],
                'refundable' => $r['refundable'],
                'fare' => ['total' => $r['fare']['display_total'], 'currency' => $r['pricing']['currency'] ?? 'INR'],
            ]),
            'meta' => ['count' => $response['count']],
        ]);
    }

    public function quote(Request $request)
    {
        $validated = $request->validate([
            'result_id' => 'required|string',
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3',
            'departure' => 'required|date',
            'adults' => 'nullable|integer|min:1|max:9',
            'children' => 'nullable|integer|min:0|max:9',
            'cabin_class' => 'nullable|string',
        ]);

        $search = $this->engine->search([
            'from' => $validated['from'],
            'to' => $validated['to'],
            'departure' => $validated['departure'],
            'adults' => $validated['adults'] ?? 1,
            'children' => $validated['children'] ?? 0,
            'cabin_class' => $validated['cabin_class'] ?? 'economy',
        ]);

        $flight = collect($search['results'])->firstWhere('result_id', $validated['result_id']);

        if (! $flight) {
            return response()->json(['error' => 'Flight fare expired or unavailable. Please re-search.'], 404);
        }

        return response()->json([
            'data' => [
                'result_id' => $flight['result_id'],
                'airline' => $flight['airline'],
                'flight_number' => $flight['flight_number'],
                'segments' => $flight['segments'],
                'fare' => $flight['fare'],
                'pricing' => $flight['pricing'],
                'refundable' => $flight['refundable'],
                'valid_until' => now()->addMinutes(15)->toIso8601String(),
            ],
        ]);
    }
}

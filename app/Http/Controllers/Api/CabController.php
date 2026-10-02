<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CabService;
use Illuminate\Http\Request;

class CabController extends Controller
{
    public function __construct(protected CabService $cabService)
    {
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'pickup' => 'required|string|max:100',
            'drop' => 'required|string|max:100',
            'pickup_date' => 'required|date|after_or_equal:today',
            'pickup_time' => 'required|date_format:H:i',
            'trip_type' => 'required|in:one_way,round_trip,local_rental,airport_transfer',
        ]);

        $search = $this->cabService->search($validated);

        return response()->json([
            'data' => collect($search['results'])->map(fn ($v) => [
                'vehicle_id' => $v['vehicle_id'],
                'name' => $v['name'],
                'type' => $v['type'],
                'capacity' => $v['passenger_capacity'],
                'fare' => ['amount' => $v['display_price'], 'currency' => 'INR'],
            ]),
            'meta' => ['count' => $search['count'], 'distance_km' => $search['distance_km']],
        ]);
    }
}

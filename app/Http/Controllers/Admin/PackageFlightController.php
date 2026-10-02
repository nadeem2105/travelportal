<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageFlightOption;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

/**
 * Admin configuration of package flight options (MakeMyTrip-style
 * with/without flights). Additive — mirrors PackageHotelController.
 */
class PackageFlightController extends Controller
{
    public function storeOption(Request $request, Package $package)
    {
        $option = $package->flightOptions()->create($this->validated($request));

        $this->enforceSingleDefault($option);

        ActivityLogger::log('create', 'packages', "Added flight option to {$package->name}");

        return back()->with('success', 'Flight option added.');
    }

    public function updateOption(Request $request, Package $package, PackageFlightOption $option)
    {
        abort_unless($option->package_id === $package->id, 404);

        $option->update($this->validated($request));

        $this->enforceSingleDefault($option);

        ActivityLogger::log('update', 'packages', "Updated flight option of {$package->name}");

        return back()->with('success', 'Flight option updated.');
    }

    public function destroyOption(Package $package, PackageFlightOption $option)
    {
        abort_unless($option->package_id === $package->id, 404);

        $option->delete();

        ActivityLogger::log('delete', 'packages', "Removed flight option of {$package->name}");

        return back()->with('success', 'Flight option removed.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'label' => 'nullable|string|max:150',
            'origin_city' => 'nullable|string|max:80',
            'origin_airport_code' => 'nullable|string|max:8',
            'destination_airport_code' => 'nullable|string|max:8',
            'airline' => 'nullable|string|max:80',
            'airline_code' => 'nullable|string|max:8',
            'trip_type' => 'required|in:one_way,round_trip',
            'cabin_class' => 'required|in:economy,premium_economy,business',
            'baggage' => 'nullable|string|max:120',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'supplier_fare_code' => 'nullable|string|max:120',
            'price_basis' => 'required|in:per_person,per_booking',
            'price' => 'required|numeric|min:0',
            'is_default' => 'nullable|boolean',
            'refundable' => 'nullable|boolean',
            'cancellation_policy' => 'nullable|string|max:2000',
            'available_from' => 'nullable|date',
            'available_to' => 'nullable|date|after_or_equal:available_from',
            'status' => 'required|in:active,inactive',
            'sort_order' => 'nullable|integer|min:0|max:255',
        ]);

        $data['is_default'] = $request->boolean('is_default');
        $data['refundable'] = $request->boolean('refundable');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }

    /** Only one recommended (default) flight option per package. */
    protected function enforceSingleDefault(PackageFlightOption $option): void
    {
        if (! $option->is_default) {
            return;
        }

        PackageFlightOption::query()
            ->where('package_id', $option->package_id)
            ->whereKeyNot($option->id)
            ->update(['is_default' => false]);
    }
}

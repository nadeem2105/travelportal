<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageHotelOption;
use App\Models\PackageHotelSegment;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

/**
 * Admin configuration of package hotel selection: segments (legs of stay) and
 * the selectable hotel options under each. Purely additive — does not touch the
 * legacy package_hotels display list.
 */
class PackageHotelController extends Controller
{
    // ---- Segments -----------------------------------------------------------

    public function storeSegment(Request $request, Package $package)
    {
        $data = $request->validate([
            'label' => 'required|string|max:120',
            'city' => 'nullable|string|max:120',
            'day_from' => 'nullable|integer|min:1|max:60',
            'day_to' => 'nullable|integer|min:1|max:60|gte:day_from',
            'nights' => 'required|integer|min:1|max:60',
            'sort_order' => 'nullable|integer|min:0|max:255',
        ]);

        $package->hotelSegments()->create($data + ['sort_order' => $data['sort_order'] ?? $package->hotelSegments()->count()]);

        ActivityLogger::log('create', 'packages', "Added hotel segment '{$data['label']}' to {$package->name}");

        return back()->with('success', 'Hotel segment added.');
    }

    public function updateSegment(Request $request, Package $package, PackageHotelSegment $segment)
    {
        abort_unless($segment->package_id === $package->id, 404);

        $data = $request->validate([
            'label' => 'required|string|max:120',
            'city' => 'nullable|string|max:120',
            'day_from' => 'nullable|integer|min:1|max:60',
            'day_to' => 'nullable|integer|min:1|max:60|gte:day_from',
            'nights' => 'required|integer|min:1|max:60',
            'sort_order' => 'nullable|integer|min:0|max:255',
        ]);

        $segment->update($data);

        ActivityLogger::log('update', 'packages', "Updated hotel segment '{$segment->label}' of {$package->name}");

        return back()->with('success', 'Hotel segment updated.');
    }

    public function destroySegment(Package $package, PackageHotelSegment $segment)
    {
        abort_unless($segment->package_id === $package->id, 404);

        $segment->delete(); // cascades to its options

        ActivityLogger::log('delete', 'packages', "Removed hotel segment '{$segment->label}' of {$package->name}");

        return back()->with('success', 'Hotel segment removed.');
    }

    // ---- Options ------------------------------------------------------------

    public function storeOption(Request $request, Package $package)
    {
        $data = $this->validateOption($request, $package);

        $option = $package->hotelOptions()->create($data);

        $this->enforceSingleDefault($option);

        ActivityLogger::log('create', 'packages', "Added hotel option to {$package->name}");

        return back()->with('success', 'Hotel option added.');
    }

    public function updateOption(Request $request, Package $package, PackageHotelOption $option)
    {
        abort_unless($option->package_id === $package->id, 404);

        $option->update($this->validateOption($request, $package));

        $this->enforceSingleDefault($option);

        ActivityLogger::log('update', 'packages', "Updated hotel option of {$package->name}");

        return back()->with('success', 'Hotel option updated.');
    }

    public function destroyOption(Package $package, PackageHotelOption $option)
    {
        abort_unless($option->package_id === $package->id, 404);

        $option->delete();

        ActivityLogger::log('delete', 'packages', "Removed hotel option of {$package->name}");

        return back()->with('success', 'Hotel option removed.');
    }

    // ---- Helpers ------------------------------------------------------------

    protected function validateOption(Request $request, Package $package): array
    {
        $data = $request->validate([
            'segment_id' => 'nullable|integer|exists:package_hotel_segments,id',
            'hotel_id' => 'nullable|integer|exists:hotels,id',
            'hotel_room_id' => 'nullable|integer|exists:hotel_rooms,id',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'supplier_hotel_code' => 'nullable|string|max:120',
            'supplier_room_code' => 'nullable|string|max:120',
            'label' => 'nullable|string|max:150',
            'room_type' => 'nullable|string|max:120',
            'meal_plan' => 'required|in:room_only,breakfast,half_board,full_board',
            'star_rating' => 'nullable|integer|min:1|max:5',
            'base_adults' => 'required|integer|min:1|max:10',
            'max_adults' => 'required|integer|min:1|max:12',
            'max_children' => 'required|integer|min:0|max:10',
            'extra_bed_allowed' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'price_basis' => 'required|in:per_person,per_room,per_booking',
            'upgrade_price' => 'nullable|numeric|min:0',
            'extra_adult_price' => 'nullable|numeric|min:0',
            'extra_child_price' => 'nullable|numeric|min:0',
            'extra_bed_price' => 'nullable|numeric|min:0',
            'refundable' => 'nullable|boolean',
            'cancellation_policy' => 'nullable|string|max:2000',
            'available_from' => 'nullable|date',
            'available_to' => 'nullable|date|after_or_equal:available_from',
            'status' => 'required|in:active,inactive',
            'sort_order' => 'nullable|integer|min:0|max:255',
        ]);

        // A segment, when supplied, must belong to this package.
        if (! empty($data['segment_id'])) {
            abort_unless(
                $package->hotelSegments()->whereKey($data['segment_id'])->exists(),
                422,
                'Segment does not belong to this package.'
            );
        }

        $data['extra_bed_allowed'] = $request->boolean('extra_bed_allowed');
        $data['is_default'] = $request->boolean('is_default');
        $data['refundable'] = $request->boolean('refundable', true);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['upgrade_price'] = $data['upgrade_price'] ?? 0;

        // The included option carries no upgrade delta.
        if ($data['is_default']) {
            $data['upgrade_price'] = 0;
        }

        return $data;
    }

    /**
     * Only one default (included) option may exist per segment (or per package
     * when segments aren't used). Demote any siblings.
     */
    protected function enforceSingleDefault(PackageHotelOption $option): void
    {
        if (! $option->is_default) {
            return;
        }

        PackageHotelOption::query()
            ->where('package_id', $option->package_id)
            ->when(
                $option->segment_id,
                fn ($q) => $q->where('segment_id', $option->segment_id),
                fn ($q) => $q->whereNull('segment_id')
            )
            ->whereKeyNot($option->id)
            ->update(['is_default' => false]);
    }
}

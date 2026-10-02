<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        $perPage = in_array((int) $request->input('per_page'), [15, 25, 50, 100]) ? (int) $request->input('per_page') : 15;

        $sortable = array_merge(config('crud_fields.coupons')['columns'], ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $coupons = Coupon::query()
            ->when($request->input('q'), fn ($query, $v) => $query->where(fn ($w) => $w
                ->where('code', 'like', "%{$v}%")
                ->orWhere('description', 'like', "%{$v}%")))
            ->when($request->input('status'), fn ($query, $v) => $query->where('status', $v))
            ->when($request->input('discount_type'), fn ($query, $v) => $query->where('discount_type', $v))
            ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
            ->when(! $applySort, fn ($query) => $query->when($request->input('sort') === 'oldest', fn ($q) => $q->oldest(), fn ($q) => $q->latest()))
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.coupons.index', compact('coupons'));
    }

    public function export(Request $request): StreamedResponse
    {
        $sortable = array_merge(config('crud_fields.coupons')['columns'], ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $filename = 'coupons-' . now()->format('Ymd-His') . '.csv';

        ActivityLogger::log('export', 'coupon', 'Exported coupons CSV');

        return response()->streamDownload(function () use ($request, $applySort, $sortCol, $sortDir) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Code', 'Discount Type', 'Discount Value', 'Usage Limit', 'Used Count', 'Status', 'Ends At']);

            Coupon::query()
                ->when($request->input('q'), fn ($query, $v) => $query->where(fn ($w) => $w
                    ->where('code', 'like', "%{$v}%")
                    ->orWhere('description', 'like', "%{$v}%")))
                ->when($request->input('status'), fn ($query, $v) => $query->where('status', $v))
                ->when($request->input('discount_type'), fn ($query, $v) => $query->where('discount_type', $v))
                ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
                ->when(! $applySort, fn ($query) => $query->when($request->input('sort') === 'oldest', fn ($q) => $q->oldest(), fn ($q) => $q->latest()))
                ->chunk(500, function ($coupons) use ($out) {
                    foreach ($coupons as $coupon) {
                        fputcsv($out, [
                            $coupon->code,
                            $coupon->discount_type,
                            $coupon->discount_value,
                            $coupon->usage_limit,
                            $coupon->used_count,
                            $coupon->status,
                            $coupon->ends_at?->toDateTimeString(),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function create()
    {
        return view('admin.coupons.form', ['coupon' => new Coupon()]);
    }

    public function store(Request $request)
    {
        Coupon::create($this->validated($request, null));

        ActivityLogger::log('create', 'coupon', 'Created coupon entry');

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon created.');
    }

    public function edit(Coupon $coupon)
    {
        return view('admin.coupons.form', ['coupon' => $coupon]);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $coupon->update($this->validated($request, $coupon));

        ActivityLogger::log('update', 'coupon', 'Updated coupon entry #' . $coupon->id);

        return back()->with('success', 'Coupon updated.');
    }

    public function destroy(Coupon $coupon)
    {
        ActivityLogger::log('delete', 'coupon', 'Deleted coupon entry #' . $coupon->id);
        $coupon->delete();

        return back()->with('success', 'Coupon deleted.');
    }

    protected function validated(Request $request, ?Coupon $model): array
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', \Illuminate\Validation\Rule::unique('coupons', 'code')->ignore($model?->id)],
            'description' => 'nullable|string|max:255',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'product_types' => 'nullable|array',
            'min_booking_amount' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'per_user_limit' => 'required|integer|min:1|max:100',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'status' => 'required|in:active,inactive',
        ]);
        $validated['first_booking_only'] = $request->boolean('first_booking_only');

        return $validated;
    }
}
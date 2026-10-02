<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $affiliates = Affiliate::with('user')
            ->withCount(['clicks', 'commissions'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $commissions = AffiliateCommission::with(['affiliate.user', 'booking'])
            ->latest()
            ->paginate(20);

        return view('admin.affiliates.index', compact('affiliates', 'commissions', 'status'));
    }

    public function updateStatus(Request $request, Affiliate $affiliate)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,active,suspended',
            'commission_percent' => 'nullable|numeric|min:0|max:50',
        ]);

        $affiliate->update($validated);
        ActivityLogger::log('update', 'affiliates', "Updated affiliate {$affiliate->affiliate_code} status to {$validated['status']}");

        return back()->with('success', 'Affiliate status updated.');
    }

    public function payCommission(Request $request, AffiliateCommission $commission)
    {
        $commission->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $commission->affiliate->increment('paid_earnings', $commission->commission_amount);
        ActivityLogger::log('update', 'affiliates', "Marked affiliate commission ₹{$commission->commission_amount} as paid for booking {$commission->booking_id}");

        return back()->with('success', 'Commission marked as paid.');
    }
}

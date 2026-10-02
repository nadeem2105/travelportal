<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PricingRule;
use App\Models\Supplier;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class PricingRuleController extends Controller
{
    public function index()
    {
        $rules = PricingRule::with('supplier')->orderBy('product_type')->latest()->get();
        $suppliers = Supplier::orderBy('name')->get();

        return view('admin.pricing.rules', compact('rules', 'suppliers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'product_type' => 'required|in:all,flight,hotel,cab,package',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'rule_type' => 'required|in:markup,commission,service_fee,convenience_fee,discount',
            'calculation' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'priority' => 'nullable|integer|min:0|max:999',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'status' => 'required|in:active,inactive',
        ]);

        PricingRule::create($validated);

        ActivityLogger::log('create', 'pricing', "Created pricing rule {$validated['name']}");

        return back()->with('success', 'Pricing rule created.');
    }

    public function update(Request $request, PricingRule $pricingRule)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'value' => 'required|numeric|min:0',
            'status' => 'required|in:active,inactive',
        ]);

        $pricingRule->update($validated);

        ActivityLogger::log('update', 'pricing', "Updated pricing rule {$pricingRule->name}");

        return back()->with('success', 'Pricing rule updated.');
    }

    public function destroy(PricingRule $pricingRule)
    {
        ActivityLogger::log('delete', 'pricing', "Deleted pricing rule {$pricingRule->name}");
        $pricingRule->delete();

        return back()->with('success', 'Pricing rule deleted.');
    }
}

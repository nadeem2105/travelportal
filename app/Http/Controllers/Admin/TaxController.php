<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tax;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    public function index()
    {
        $taxes = Tax::orderBy('product_type')->get();

        return view('admin.pricing.taxes', compact('taxes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'product_type' => 'required|in:all,flight,hotel,cab,package',
            'calculation' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'is_inclusive' => 'nullable|boolean',
            'description' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        $validated['is_inclusive'] = $request->boolean('is_inclusive');

        Tax::create($validated);

        ActivityLogger::log('create', 'taxes', "Created tax {$validated['name']}");

        return back()->with('success', 'Tax created.');
    }

    public function update(Request $request, Tax $tax)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'value' => 'required|numeric|min:0',
            'status' => 'required|in:active,inactive',
        ]);

        $tax->update($validated);

        ActivityLogger::log('update', 'taxes', "Updated tax {$tax->name}");

        return back()->with('success', 'Tax updated.');
    }

    public function destroy(Tax $tax)
    {
        ActivityLogger::log('delete', 'taxes', "Deleted tax {$tax->name}");
        $tax->delete();

        return back()->with('success', 'Tax deleted.');
    }
}

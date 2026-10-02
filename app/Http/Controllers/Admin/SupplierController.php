<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierCredential;
use App\Services\ActivityLogger;
use App\Services\Suppliers\SupplierAdapterRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::with('credentials')->orderBy('type')->orderBy('priority')->get();
        $adapters = array_keys(SupplierAdapterRegistry::FLIGHT_ADAPTERS + SupplierAdapterRegistry::HOTEL_ADAPTERS);

        return view('admin.suppliers.index', compact('suppliers', 'adapters'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:flight,hotel,cab,package,bus,generic',
            'adapter' => 'required|string|max:50',
            'environment' => 'required|in:test,production',
            'priority' => 'required|integer|min:0|max:999',
            'timeout_seconds' => 'required|integer|min:5|max:120',
            'retry_attempts' => 'required|integer|min:0|max:5',
            'default_markup_percent' => 'nullable|numeric|min:0|max:100',
            'default_commission_percent' => 'nullable|numeric|min:0|max:100',
            'default_service_fee' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:500',
        ]);

        $supplier = Supplier::create($validated + ['slug' => Str::slug($validated['name']), 'status' => 'active']);

        ActivityLogger::log('create', 'suppliers', "Created supplier {$supplier->name}");

        return back()->with('success', 'Supplier added. Add its API credentials next.');
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'environment' => 'required|in:test,production',
            'priority' => 'required|integer|min:0|max:999',
            'timeout_seconds' => 'required|integer|min:5|max:120',
            'retry_attempts' => 'required|integer|min:0|max:5',
            'default_markup_percent' => 'nullable|numeric|min:0|max:100',
            'default_commission_percent' => 'nullable|numeric|min:0|max:100',
            'default_service_fee' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,inactive',
            'description' => 'nullable|string|max:500',
            'settings' => 'nullable|string',
        ]);

        if (! empty($validated['settings'])) {
            $decoded = json_decode($validated['settings'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->with('error', 'Supplier settings must be valid JSON.');
            }
            $validated['settings'] = $decoded;
        } else {
            $validated['settings'] = null;
        }

        $supplier->update($validated);

        ActivityLogger::log('update', 'suppliers', "Updated supplier {$supplier->name}");

        return back()->with('success', 'Supplier updated.');
    }

    /**
     * Credentials are stored encrypted; never returned to the UI once saved.
     */
    public function saveCredentials(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'environment' => 'required|in:test,production',
            'credentials' => 'required|string',
        ]);

        $decoded = json_decode($validated['credentials'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return back()->with('error', 'Credentials must be valid JSON, e.g. {"api_key": "...", "api_secret": "..."}.');
        }

        SupplierCredential::updateOrCreate(
            ['supplier_id' => $supplier->id, 'environment' => $validated['environment']],
            ['credentials' => $decoded, 'is_active' => true]
        );

        ActivityLogger::log('update', 'suppliers', "Credentials updated for {$supplier->name} ({$validated['environment']})");

        return back()->with('success', 'Credentials saved (encrypted at rest).');
    }

    public function destroy(Supplier $supplier)
    {
        ActivityLogger::log('delete', 'suppliers', "Deleted supplier {$supplier->name}");
        $supplier->delete();

        return back()->with('success', 'Supplier deleted.');
    }
}

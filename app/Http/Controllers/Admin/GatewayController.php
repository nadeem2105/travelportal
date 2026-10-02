<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class GatewayController extends Controller
{
    public function index()
    {
        $gateways = PaymentGateway::orderBy('sort_order')->get();
        $secretFields = [
            'razorpay' => ['key_id', 'key_secret', 'webhook_secret'],
            'mock' => ['secret', 'webhook_secret'],
        ];

        return view('admin.gateways.index', compact('gateways', 'secretFields'));
    }

    public function update(Request $request, PaymentGateway $gateway)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'mode' => 'required|in:test,live',
            'currency' => 'required|string|size:3',
            'is_enabled' => 'nullable|boolean',
            'config' => 'nullable|array',
        ]);

        $config = $gateway->config ?? [];

        // Merge only submitted keys; empty secret inputs keep stored values.
        foreach ($validated['config'] ?? [] as $key => $value) {
            if ($value !== null && $value !== '') {
                $config[$key] = $value;
            }
        }

        $gateway->update([
            'name' => $validated['name'],
            'mode' => $validated['mode'],
            'currency' => $validated['currency'],
            'is_enabled' => $request->boolean('is_enabled'),
            'config' => $config,
        ]);

        ActivityLogger::log('update', 'payments', "Gateway {$gateway->code} updated (enabled: " . ($gateway->is_enabled ? 'yes' : 'no') . ')');

        return back()->with('success', 'Gateway saved. Secrets are encrypted at rest.');
    }
}

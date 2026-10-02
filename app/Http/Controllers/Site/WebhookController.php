<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(protected PaymentManager $payments)
    {
    }

    /**
     * Razorpay webhook. Raw body is required for signature verification.
     */
    public function razorpay(Request $request)
    {
        $signature = $request->header('X-Razorpay-Signature');

        $result = $this->payments->handleWebhook('razorpay', $request->getContent(), $signature);

        if (! $result['success']) {
            return response()->json(['error' => $result['error'] ?? 'Rejected'], 400);
        }

        return response()->json(['ok' => true] + ($result['duplicate'] ?? false ? ['duplicate' => true] : []));
    }
}

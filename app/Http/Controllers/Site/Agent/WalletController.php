<?php

namespace App\Http\Controllers\Site\Agent;

use App\Http\Controllers\Controller;

class WalletController extends Controller
{
    public function index()
    {
        $agent = auth('agent')->user();

        $transactions = $agent->transactions()
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('agent.wallet', [
            'seo' => ['title' => 'Wallet & Ledger'],
            'agent' => $agent,
            'transactions' => $transactions,
        ]);
    }
}

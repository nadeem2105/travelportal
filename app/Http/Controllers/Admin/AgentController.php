<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentTransaction;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $q = trim((string) $request->query('q'));

        $agents = Agent::with('user')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($q, fn ($query) => $query->where(fn ($w) => $w
                ->where('agency_name', 'like', "%{$q}%")
                ->orWhere('agency_code', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.agents.index', compact('agents', 'status', 'q'));
    }

    public function create()
    {
        return view('admin.agents.form', ['agent' => new Agent()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'agency_name' => 'required|string|max:120',
            'contact_person' => 'required|string|max:100',
            'email' => 'required|email|unique:agents,email',
            'phone' => 'required|string|max:20',
            'city' => 'nullable|string|max:60',
            'address' => 'nullable|string|max:500',
            'pan_number' => 'nullable|string|max:20',
            'gst_number' => 'nullable|string|max:20',
            'credit_limit' => 'nullable|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'markup_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $count = Agent::count() + 1;
        $data['agency_code'] = 'AGT-' . str_pad($count, 4, '0', STR_PAD_LEFT);
        $data['status'] = 'approved';
        $data['approved_by'] = auth('admin')->id();
        $data['approved_at'] = now();

        $agent = Agent::create($data);
        ActivityLogger::log('create', 'agents', "Registered B2B Agent: {$agent->agency_name} ({$agent->agency_code})");

        return redirect()->route('admin.agents.show', $agent)->with('success', "Agent {$agent->agency_code} registered successfully.");
    }

    public function show(Agent $agent)
    {
        $agent->load(['user', 'approver']);
        $transactions = $agent->transactions()->latest()->paginate(20);

        return view('admin.agents.show', compact('agent', 'transactions'));
    }

    public function updateStatus(Request $request, Agent $agent)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,approved,suspended,rejected',
            'rejection_reason' => 'nullable|string|max:255',
        ]);

        $agent->update([
            'status' => $validated['status'],
            'approved_by' => $validated['status'] === 'approved' ? auth('admin')->id() : $agent->approved_by,
            'approved_at' => $validated['status'] === 'approved' ? now() : $agent->approved_at,
            'rejection_reason' => $validated['status'] === 'rejected' ? ($validated['rejection_reason'] ?? null) : null,
        ]);

        ActivityLogger::log('update', 'agents', "Updated agent {$agent->agency_code} status to {$validated['status']}");

        return back()->with('success', "Agent status updated to {$validated['status']}.");
    }

    /**
     * Set or reset an agent's login password from the admin panel. Lets admins
     * onboard agents who applied without a password, or help those locked out.
     */
    public function setPassword(Request $request, Agent $agent)
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)],
        ]);

        $agent->update(['password' => $validated['password']]);

        ActivityLogger::log('update', 'agents', "Set/reset login password for agent {$agent->agency_code}");

        return back()->with('success', "Login password updated for {$agent->agency_code}.");
    }

    public function adjustBalance(Request $request, Agent $agent)
    {
        $validated = $request->validate([
            'type' => 'required|in:deposit,credit_adjustment',
            'amount' => 'required|numeric|min:1',
            'notes' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($agent, $validated) {
            $amount = (float) $validated['amount'];
            if ($validated['type'] === 'deposit') {
                $newBalance = (float) $agent->wallet_balance + $amount;
                $agent->update(['wallet_balance' => $newBalance]);
            } else {
                $newBalance = (float) $agent->credit_limit + $amount;
                $agent->update(['credit_limit' => $newBalance]);
            }

            AgentTransaction::create([
                'agent_id' => $agent->id,
                'type' => $validated['type'],
                'amount' => $amount,
                'balance_after' => $newBalance,
                'notes' => $validated['notes'],
                'created_by' => auth('admin')->id(),
            ]);
        });

        ActivityLogger::log('update', 'agents', "Adjusted balance for {$agent->agency_code}: {$validated['type']} ₹{$validated['amount']}");

        return back()->with('success', 'Agent balance adjusted successfully.');
    }
}

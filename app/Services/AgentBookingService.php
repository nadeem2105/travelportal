<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;

/**
 * Handles agent-attributed bookings: computes the agent's net price
 * (public price minus agent commission) and debits the agent's wallet /
 * credit line atomically when the booking is created.
 */
class AgentBookingService
{
    /**
     * Compute the agent economics for a public-facing total.
     *
     *  - commission = total * commission_rate%   (the agent's earning / discount)
     *  - net_payable = total - commission          (what the agent actually pays us)
     */
    public function computeAgentPricing(Agent $agent, float $publicTotal): array
    {
        $commissionRate = (float) $agent->commission_rate;
        $commission = round($publicTotal * ($commissionRate / 100), 2);
        $netPayable = round($publicTotal - $commission, 2);

        return [
            'public_total' => round($publicTotal, 2),
            'commission_rate' => $commissionRate,
            'commission' => $commission,
            'net_payable' => $netPayable,
        ];
    }

    /**
     * Does the agent have enough wallet + available credit to cover this amount?
     */
    public function canAfford(Agent $agent, float $netPayable): bool
    {
        return $agent->totalPurchasingPower() >= $netPayable;
    }

    /**
     * Debit the agent for a created booking: draw from wallet first, then credit
     * line. Records ledger transactions and the earned commission. Runs in a
     * transaction and locks the agent row to avoid double-spend.
     *
     * @throws \DomainException when funds are insufficient
     */
    public function debitForBooking(Agent $agent, Booking $booking, float $netPayable, float $commission): void
    {
        DB::transaction(function () use ($agent, $booking, $netPayable, $commission) {
            /** @var Agent $locked */
            $locked = Agent::whereKey($agent->id)->lockForUpdate()->first();

            $wallet = (float) $locked->wallet_balance;
            $availableCredit = $locked->availableCredit();

            if (($wallet + max(0, $availableCredit)) < $netPayable) {
                throw new \DomainException('Insufficient wallet balance and credit to complete this booking.');
            }

            $fromWallet = min($wallet, $netPayable);
            $fromCredit = round($netPayable - $fromWallet, 2);

            $newWallet = round($wallet - $fromWallet, 2);
            $newCreditBalance = round((float) $locked->credit_balance + $fromCredit, 2);

            $locked->update([
                'wallet_balance' => $newWallet,
                'credit_balance' => $newCreditBalance,
            ]);

            // Ledger: wallet debit
            if ($fromWallet > 0) {
                $locked->transactions()->create([
                    'type' => 'booking_debit',
                    'amount' => $fromWallet,
                    'balance_after' => $newWallet,
                    'booking_id' => $booking->id,
                    'reference' => $booking->booking_reference,
                    'notes' => 'Booking payment (wallet)',
                ]);
            }

            // Ledger: credit-line draw
            if ($fromCredit > 0) {
                $locked->transactions()->create([
                    'type' => 'credit_adjustment',
                    'amount' => $fromCredit,
                    'balance_after' => $newWallet,
                    'booking_id' => $booking->id,
                    'reference' => $booking->booking_reference,
                    'notes' => 'Booking payment (credit line). Credit used: '.$newCreditBalance,
                ]);
            }

            // Ledger: commission earned
            if ($commission > 0) {
                $locked->transactions()->create([
                    'type' => 'commission',
                    'amount' => $commission,
                    'balance_after' => $newWallet,
                    'booking_id' => $booking->id,
                    'reference' => $booking->booking_reference,
                    'notes' => 'Commission earned on booking',
                ]);
            }
        });
    }
}

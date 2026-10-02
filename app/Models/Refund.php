<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $fillable = [
        'booking_id', 'payment_id', 'gateway_refund_id', 'amount', 'penalty_amount',
        'reason', 'status', 'requested_by', 'processed_by', 'processed_at', 'gateway_response',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'gateway_response' => 'array',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function processor()
    {
        return $this->belongsTo(Admin::class, 'processed_by');
    }
}

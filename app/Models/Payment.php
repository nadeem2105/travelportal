<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;
    protected $fillable = [
        'booking_id', 'gateway', 'gateway_order_id', 'gateway_payment_id',
        'gateway_signature', 'amount', 'currency', 'status', 'method',
        'payload', 'webhook_payload', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'webhook_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }
}

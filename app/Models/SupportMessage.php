<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportMessage extends Model
{
    protected $fillable = [
        'ticket_id', 'sender_type', 'sender_id', 'message', 'attachments', 'is_internal_note',
    ];

    protected function casts(): array
    {
        return ['attachments' => 'array', 'is_internal_note' => 'boolean'];
    }

    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }
}

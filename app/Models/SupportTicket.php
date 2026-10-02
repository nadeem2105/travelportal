<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    protected $fillable = [
        'ticket_no', 'user_id', 'booking_id', 'name', 'email', 'subject',
        'priority', 'status', 'assigned_to',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignee()
    {
        return $this->belongsTo(Admin::class, 'assigned_to');
    }

    public function messages()
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id')->where('is_internal_note', false)->oldest();
    }

    public function allMessages()
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id')->oldest();
    }
}

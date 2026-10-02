<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'conversation_id', 'contact_id', 'wa_message_id', 'direction', 'type',
        'body', 'media', 'template_name', 'status', 'error', 'sent_by', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'media' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'sent_by');
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }
}

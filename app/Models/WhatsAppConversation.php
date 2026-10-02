<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppConversation extends Model
{
    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'contact_id', 'assigned_to', 'wa_id', 'profile_name', 'last_message_at',
        'last_message_preview', 'last_message_direction', 'unread_count',
        'window_expires_at', 'status', 'bot_paused',
        'verified_user_id', 'verified_contact_id', 'verified_at', 'assistant_context',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'window_expires_at' => 'datetime',
            'bot_paused' => 'boolean',
            'verified_at' => 'datetime',
            'assistant_context' => 'array',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function verifiedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_user_id');
    }

    public function verifiedContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'verified_contact_id');
    }

    /**
     * True once the sender behind this WhatsApp number has proven identity.
     * Verification means the number was confirmed (registered-phone match or a
     * booking reference bound to this number). Which specific bookings are
     * accessible is scoped separately in CustomerResolver (by user_id + phone),
     * so a guest with no CRM user/contact is still validly verified.
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'conversation_id')->orderBy('created_at');
    }

    /** True when a free-form (non-template) message may be sent right now. */
    public function isWindowOpen(): bool
    {
        return $this->window_expires_at !== null && $this->window_expires_at->isFuture();
    }
}

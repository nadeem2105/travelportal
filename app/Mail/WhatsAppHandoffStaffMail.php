<?php

namespace App\Mail;

use App\Models\WhatsAppAutoReply;
use App\Models\WhatsAppConversation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WhatsAppHandoffStaffMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public WhatsAppConversation $conversation,
        public WhatsAppAutoReply $rule,
        public ?string $inboundText = null
    ) {
        $this->conversation->loadMissing(['contact', 'assignee']);
    }

    public function envelope(): Envelope
    {
        $customer = $this->conversation->contact?->name
            ?: ($this->conversation->profile_name ?: $this->conversation->wa_id);

        $company = settings('company_name', 'TravelQue Cashmir');

        return new Envelope(
            subject: "🚨 WhatsApp Chat Hand-off: {$customer} ({$this->conversation->wa_id}) requires human agent - {$company}"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.whatsapp_handoff_staff',
            with: [
                'conversation' => $this->conversation,
                'rule' => $this->rule,
                'inboundText' => $this->inboundText,
                'customerName' => $this->conversation->contact?->name ?: ($this->conversation->profile_name ?: 'Customer'),
                'inboxUrl' => url('/admin/whatsapp?c=' . $this->conversation->id),
            ]
        );
    }
}

<?php

namespace App\Services\WhatsApp;

use App\Models\Contact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\Crm\ContactService;
use App\Services\Crm\CrmActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orchestrates WhatsApp messaging on top of the transport client: resolves the
 * CRM contact, persists conversations + messages, maintains the 24-hour service
 * window, and records CRM activity. This is the single entry point the rest of
 * the app (inbox, notifications, automation) should use.
 */
class WhatsAppService
{
    public function __construct(
        private WhatsAppCloudClient $client,
        private ContactService $contacts,
        private CrmActivityService $activity,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.whatsapp.enabled') && $this->client->isConfigured();
    }

    /** Resolve (or create) the conversation for a customer WhatsApp id. */
    public function conversationFor(string $waId, ?string $profileName = null): WhatsAppConversation
    {
        $waId = preg_replace('/\D+/', '', $waId);

        $conversation = WhatsAppConversation::firstOrNew(['wa_id' => $waId]);

        if (! $conversation->exists) {
            // Link to a CRM contact by the same normalized phone (create if new).
            $contact = $this->contacts->findOrCreate([
                'name' => $profileName ?: $waId,
                'phone' => $waId,
                'whatsapp_opt_in' => true,
            ]);
            $conversation->contact_id = $contact->id;
        }

        if ($profileName && ! $conversation->profile_name) {
            $conversation->profile_name = $profileName;
        }

        $conversation->save();

        return $conversation;
    }

    /**
     * Persist an inbound customer message and (re)open the 24h window.
     *
     * @param  array  $msg  Normalized: type, body, media[], wa_message_id, timestamp
     */
    public function recordInbound(string $waId, ?string $profileName, array $msg): WhatsAppMessage
    {
        return DB::transaction(function () use ($waId, $profileName, $msg) {
            $conversation = $this->conversationFor($waId, $profileName);

            $message = WhatsAppMessage::create([
                'conversation_id' => $conversation->id,
                'contact_id' => $conversation->contact_id,
                'wa_message_id' => $msg['wa_message_id'] ?? null,
                'direction' => 'inbound',
                'type' => $msg['type'] ?? 'text',
                'body' => $msg['body'] ?? null,
                'media' => $msg['media'] ?? null,
                'status' => 'received',
                'sent_at' => $msg['timestamp'] ?? now(),
            ]);

            $conversation->forceFill([
                'last_message_at' => $message->sent_at,
                'last_message_preview' => Str::limit($msg['body'] ?? ('[' . ($msg['type'] ?? 'message') . ']'), 120),
                'last_message_direction' => 'inbound',
                'unread_count' => $conversation->unread_count + 1,
                'window_expires_at' => now()->addHours(24), // customer message opens the window
                'status' => 'open',
            ])->save();

            if ($conversation->contact_id) {
                Contact::where('id', $conversation->contact_id)->update(['last_activity_at' => now()]);
                $this->logActivity($conversation, 'whatsapp_in', 'WhatsApp received', $msg['body'] ?? null);

                // Fire automation workflows for inbound WhatsApp.
                $lead = \App\Models\CrmLead::where('contact_id', $conversation->contact_id)->latest()->first();
                if ($lead) {
                    app(\App\Services\Crm\AutomationEngine::class)->dispatch('whatsapp_received', $lead);
                }
            }

            return $message;
        });
    }

    /** Send a free-form text (requires an open 24h window). */
    public function sendText(WhatsAppConversation $conversation, string $body, ?int $adminId = null): array
    {
        if (! $conversation->isWindowOpen()) {
            return ['success' => false, 'error' => 'The 24-hour window has closed. Send an approved template to re-open the conversation.'];
        }

        $result = $this->client->sendText($conversation->wa_id, $body);

        $this->persistOutbound($conversation, [
            'type' => 'text',
            'body' => $body,
        ], $result, $adminId);

        return $result;
    }

    /**
     * Send an interactive button message (requires an open 24h window).
     * Supports Header (text or image), Body, Footer, and up to 3 Quick Reply Buttons.
     */
    public function sendInteractiveButtons(WhatsAppConversation $conversation, string $body, array $buttons, ?string $header = null, ?string $footer = null, ?string $headerType = 'text', ?string $headerImageUrl = null, ?int $adminId = null): array
    {
        if (! $conversation->isWindowOpen()) {
            return ['success' => false, 'error' => 'The 24-hour window has closed. Send an approved template to re-open the conversation.'];
        }

        $result = $this->client->sendInteractiveButtons($conversation->wa_id, $body, $buttons, $header, $footer, $headerType, $headerImageUrl);

        $buttonTitles = array_map(fn ($b) => is_array($b) ? ($b['title'] ?? '') : (string) $b, $buttons);
        $headerPreview = '';
        if ($headerType === 'image' && $headerImageUrl) {
            $headerPreview = "[Image Header: {$headerImageUrl}]\n";
        } elseif ($header && trim($header) !== '') {
            $headerPreview = "[{$header}]\n";
        }
        $preview = $headerPreview . $body . ($footer ? "\n_{$footer}_" : '') . "\n[Buttons: " . implode(' | ', $buttonTitles) . ']';

        $this->persistOutbound($conversation, [
            'type' => 'interactive',
            'body' => $preview,
            'media' => [
                'interactive_type' => 'button',
                'header_type' => $headerType,
                'header_image_url' => $headerImageUrl,
                'header' => $header,
                'footer' => $footer,
                'buttons' => $buttons,
            ],
        ], $result, $adminId);

        return $result;
    }

    /**
     * Send a media message by public URL (image, video, document) (requires an open 24h window).
     */
    public function sendMediaLink(WhatsAppConversation $conversation, string $type, string $link, ?string $caption = null, ?int $adminId = null): array
    {
        if (! $conversation->isWindowOpen()) {
            return ['success' => false, 'error' => 'The 24-hour window has closed. Send an approved template first to re-open the conversation.'];
        }

        $result = $this->client->sendMedia($conversation->wa_id, $type, $link, $caption);

        $this->persistOutbound($conversation, [
            'type' => $type,
            'body' => $caption ?: $link,
            'media' => ['link' => $link, 'caption' => $caption],
        ], $result, $adminId);

        return $result;
    }

    /**
     * Send an interactive list menu message (requires an open 24h window).
     */
    public function sendInteractiveList(WhatsAppConversation $conversation, string $body, string $buttonLabel, array $sections, ?string $header = null, ?string $footer = null, ?int $adminId = null): array
    {
        if (! $conversation->isWindowOpen()) {
            return ['success' => false, 'error' => 'The 24-hour window has closed. Send an approved template to re-open the conversation.'];
        }

        $result = $this->client->sendInteractiveList($conversation->wa_id, $body, $buttonLabel, $sections, $header, $footer);

        $preview = ($header ? "[{$header}]\n" : '') . $body . ($footer ? "\n_{$footer}_" : '') . " [Menu: {$buttonLabel}]";

        $this->persistOutbound($conversation, [
            'type' => 'interactive',
            'body' => $preview,
            'media' => [
                'interactive_type' => 'list',
                'header' => $header,
                'footer' => $footer,
                'button' => $buttonLabel,
                'sections' => $sections,
            ],
        ], $result, $adminId);

        return $result;
    }

    /**
     * Upload a file and send it as a document message (requires an open 24h window).
     * Persists a document message on the conversation.
     */
    public function sendDocument(WhatsAppConversation $conversation, string $bytes, string $filename, ?string $mime = 'application/pdf', ?string $caption = null, ?int $adminId = null): array
    {
        if (! $conversation->isWindowOpen()) {
            return ['success' => false, 'error' => 'The 24-hour window has closed. Send an approved template first to re-open the conversation.'];
        }

        $upload = $this->client->uploadMedia($bytes, $mime ?: 'application/pdf', $filename);
        if (! ($upload['success'] ?? false)) {
            return ['success' => false, 'error' => $upload['error'] ?? 'Upload failed'];
        }

        $result = $this->client->sendDocument($conversation->wa_id, $upload['id'], $filename, $caption);

        $this->persistOutbound($conversation, [
            'type' => 'document',
            'body' => $caption ?: $filename,
            'media' => ['filename' => $filename, 'mime' => $mime ?: 'application/pdf', 'media_id' => $upload['id']],
        ], $result, $adminId);

        return $result;
    }

    /**
     * Upload a PDF and send it as a document message (requires an open 24h window).
     * Persists a document message on the conversation.
     */
    public function sendDocumentPdf(WhatsAppConversation $conversation, string $pdfBytes, string $filename, ?string $caption = null, ?int $adminId = null): array
    {
        return $this->sendDocument($conversation, $pdfBytes, $filename, 'application/pdf', $caption, $adminId);
    }

    /** Send an approved template (allowed anytime). */
    public function sendTemplate(WhatsAppConversation $conversation, string $template, ?string $lang = null, array $components = [], ?int $adminId = null): array
    {
        $result = $this->client->sendTemplate($conversation->wa_id, $template, $lang, $components);

        $this->persistOutbound($conversation, [
            'type' => 'template',
            'body' => $this->componentsPreview($template, $components),
            'template_name' => $template,
        ], $result, $adminId);

        return $result;
    }

    /**
     * Transactional convenience: send an approved template to a phone number
     * (resolving/creating the conversation). Body variables are passed as plain
     * strings in order. Safe no-op when WhatsApp is disabled or no phone given.
     *
     * @param  array<int,string>  $params  Ordered body variable values.
     */
    public function notifyTemplate(?string $phone, string $template, ?string $lang = null, array $params = [], ?string $name = null, ?array $document = null): array
    {
        if (! $this->isEnabled() || empty($phone) || empty($template)) {
            return ['success' => false, 'error' => 'skipped'];
        }

        $conversation = $this->conversationFor($phone, $name);

        $components = [];

        // Look up the approved template (synced from Meta) so we can honour its
        // real shape: how many body variables it takes, and whether its header is
        // actually a DOCUMENT header.
        $tpl = \App\Models\WhatsAppTemplate::where('name', $template)->first();

        // Optional document header — the template MUST have a document header in
        // Meta for this to be accepted. If we know (from the synced template) that
        // its header is NOT a document, attaching one guarantees a Meta rejection
        // and the whole message — text included — never arrives. In that case skip
        // the attachment so at least the text template is delivered, and log it.
        // $document = ['bytes'=>, 'filename'=>].
        $documentAttached = false;
        if ($document && ! empty($document['bytes'])) {
            $headerType = $tpl?->getHeaderType(); // 'document' | 'text' | 'image' | null | (null when not synced)
            $templateSupportsDocument = ($tpl === null) || $headerType === 'document';

            if (! $templateSupportsDocument) {
                \Illuminate\Support\Facades\Log::warning(
                    "WhatsApp template '{$template}' has no document header (header: "
                    . ($headerType ?: 'none') . ") — sending without the "
                    . ($document['filename'] ?? 'document.pdf') . ' attachment. '
                    . 'Recreate the template in Meta with a Document header to deliver the PDF.'
                );
            } else {
                $upload = $this->client->uploadMedia($document['bytes'], 'application/pdf', $document['filename'] ?? 'document.pdf');
                if ($upload['success'] ?? false) {
                    $components[] = [
                        'type' => 'header',
                        'parameters' => [[
                            'type' => 'document',
                            'document' => ['id' => $upload['id'], 'filename' => $document['filename'] ?? 'document.pdf'],
                        ]],
                    ];
                    $documentAttached = true;
                } else {
                    // Media upload failed — the template still sends (without the PDF),
                    // but log why so a missing attachment isn't invisible.
                    \Illuminate\Support\Facades\Log::warning(
                        "WhatsApp media upload failed for template '{$template}' (" . ($document['filename'] ?? 'document.pdf') . '): '
                        . ($upload['error'] ?? 'unknown error')
                    );
                }
            }
        }

        $params = array_map(fn ($v) => ($v === null || trim((string) $v) === '') ? '—' : (string) $v, $params);
        if (! empty($params)) {
            if ($tpl && $tpl->body_variable_count > 0 && count($params) > $tpl->body_variable_count) {
                $params = array_slice($params, 0, $tpl->body_variable_count);
            }

            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => $v], $params),
            ];
        }

        $result = $this->sendTemplate($conversation, $template, $lang, $components);

        // A rejected send (bad template name, header/body mismatch, unapproved
        // template) otherwise vanishes — it's only persisted as a failed
        // whatsapp_message. Surface it so a non-delivered doc is diagnosable.
        if (! ($result['success'] ?? false)) {
            \Illuminate\Support\Facades\Log::warning(
                "WhatsApp template '{$template}' send failed"
                . ($documentAttached ? ' (with document header)' : '')
                . ': ' . ($result['error'] ?? 'unknown error')
            );
        }

        return $result;
    }

    /**
     * Fire a transactional WhatsApp template for a named event, looking up the
     * approved template from config (services.whatsapp.templates.<event>). No-op
     * (and never throws) when the event has no template configured or WhatsApp is
     * off — email/SMS remain the guaranteed channels.
     *
     * @param  array<int,string>  $params  Ordered body variable values.
     */
    public function notifyEvent(string $event, ?string $phone, array $params = [], ?string $name = null, ?array $document = null): array
    {
        $template = config("services.whatsapp.templates.{$event}");
        if (empty($template)) {
            return ['success' => false, 'error' => 'no template configured'];
        }

        try {
            return $this->notifyTemplate($phone, $template, null, $params, $name, $document);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("WhatsApp event '{$event}' failed: " . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** Update the delivery status of an outbound message (from a status webhook). */
    public function recordStatus(string $waMessageId, string $status, ?string $error = null): void
    {
        WhatsAppMessage::where('wa_message_id', $waMessageId)->update(array_filter([
            'status' => $status,
            'error' => $error,
        ], fn ($v) => $v !== null));

        // Mirror onto campaign recipients so campaign progress reflects real
        // delivery/read receipts. Only advance the status (never regress).
        $rank = ['sent' => 1, 'delivered' => 2, 'read' => 3];
        $recipient = \App\Models\WhatsAppCampaignRecipient::where('wa_message_id', $waMessageId)->first();
        if ($recipient) {
            $mapped = $status === 'failed' ? 'failed' : $status;
            $current = $rank[$recipient->status] ?? 0;
            $incoming = $rank[$mapped] ?? 0;
            if ($mapped === 'failed' || $incoming > $current) {
                $recipient->update(['status' => $mapped, 'error' => $error]);
                if ($recipient->campaign) {
                    app(CampaignService::class)->refreshProgress($recipient->campaign);
                }
            }
        }
    }

    public function markConversationRead(WhatsAppConversation $conversation): void
    {
        $conversation->update(['unread_count' => 0]);
    }

    protected function persistOutbound(WhatsAppConversation $conversation, array $data, array $result, ?int $adminId): WhatsAppMessage
    {
        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $conversation->contact_id,
            'wa_message_id' => $result['wa_message_id'] ?? null,
            'direction' => 'outbound',
            'type' => $data['type'],
            'body' => $data['body'] ?? null,
            'media' => $data['media'] ?? null,
            'template_name' => $data['template_name'] ?? null,
            'status' => $result['success'] ? 'sent' : 'failed',
            'error' => $result['success'] ? null : ($result['error'] ?? 'Unknown error'),
            'sent_by' => $adminId,
            'sent_at' => now(),
        ]);

        $conversation->forceFill([
            'last_message_at' => now(),
            'last_message_preview' => Str::limit($data['body'] ?? '[template]', 120),
            'last_message_direction' => 'outbound',
        ])->save();

        if ($result['success']) {
            $this->logActivity($conversation, 'whatsapp_out', 'WhatsApp sent', $data['body'] ?? null);
        }

        return $message;
    }

    protected function logActivity(WhatsAppConversation $conversation, string $type, string $title, ?string $body): void
    {
        $lead = $conversation->contact_id
            ? \App\Models\CrmLead::where('contact_id', $conversation->contact_id)->latest()->first()
            : null;

        if ($lead) {
            $this->activity->forLead($lead, $type, $title, [
                'description' => $body,
                'performed_by' => null,
            ]);
        } elseif ($conversation->contact_id) {
            $this->activity->record($type, $title, [
                'description' => $body,
                'contact_id' => $conversation->contact_id,
                'performed_by' => null,
            ]);
        }
    }

    protected function componentsPreview(string $template, array $components): string
    {
        $params = [];
        foreach ($components as $c) {
            foreach ($c['parameters'] ?? [] as $p) {
                if (($p['type'] ?? '') === 'text') {
                    $params[] = $p['text'];
                }
            }
        }

        return "[template: {$template}]" . ($params ? ' ' . implode(' · ', $params) : '');
    }
}

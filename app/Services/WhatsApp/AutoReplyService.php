<?php

namespace App\Services\WhatsApp;

use App\Mail\WhatsAppHandoffStaffMail;
use App\Models\Admin;
use App\Models\WhatsAppAutoReply;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppTemplate;
use App\Services\ActivityLogger;
use App\Services\MailConfigService;
use App\Services\SmsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The keyword chatbot. Given an inbound message, finds the first matching active
 * rule (by priority), falling back to a default rule if configured, and sends
 * the reply through WhatsAppService. Handoff rules pause the bot for that
 * conversation so a human can take over.
 */
class AutoReplyService
{
    /** Last send error, surfaced to the admin after a manual trigger. */
    protected ?string $lastError = null;

    public function __construct(private WhatsAppService $whatsapp)
    {
    }

    /**
     * Handle an inbound text for a conversation. Returns true if a reply was sent.
     */
    public function handle(WhatsAppConversation $conversation, ?string $inboundText): bool
    {
        // Respect human handoff and disabled messaging.
        if ($conversation->bot_paused || ! $this->whatsapp->isEnabled()) {
            return false;
        }

        $text = mb_strtolower(trim((string) $inboundText));
        if ($text === '') {
            return false;
        }

        $rules = WhatsAppAutoReply::where('active', true)->orderBy('priority')->get();

        $rule = $rules->first(fn (WhatsAppAutoReply $r) => $r->matches($text))
            ?? $rules->firstWhere('is_default', true);

        if (! $rule) {
            return false;
        }

        // Handoff: pause the bot, alert staff via email/SMS, and optionally send acknowledgement.
        if ($rule->is_handoff) {
            $conversation->update(['bot_paused' => true]);
            $this->notifyStaffOfHandoff($conversation, $rule, $inboundText);
        }

        return $this->dispatchReply($conversation, $rule);
    }

    /**
     * Notify assigned staff or super admins that a WhatsApp chat has been handed off.
     */
    protected function notifyStaffOfHandoff(WhatsAppConversation $conversation, WhatsAppAutoReply $rule, ?string $inboundText): void
    {
        try {
            // 1. Audit log
            ActivityLogger::log(
                'whatsapp_handoff',
                'whatsapp',
                "WhatsApp conversation #{$conversation->id} ({$conversation->wa_id}) handed off to human agent by rule '{$rule->name}'",
                [
                    'conversation_id' => $conversation->id,
                    'wa_id' => $conversation->wa_id,
                    'customer_name' => $conversation->contact?->name ?: $conversation->profile_name,
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'inbound_text' => $inboundText,
                ]
            );

            // 2. Resolve recipient email(s)
            $recipients = [];
            if ($conversation->assignee && ! empty($conversation->assignee->email)) {
                $recipients[] = $conversation->assignee->email;
            }

            $companyEmail = settings('notification_email') ?: settings('company_email');
            if ($companyEmail && ! in_array($companyEmail, $recipients, true)) {
                $recipients[] = $companyEmail;
            }

            if (empty($recipients)) {
                $adminEmail = Admin::where('is_active', true)->whereNotNull('email')->value('email');
                if ($adminEmail) {
                    $recipients[] = $adminEmail;
                } elseif (config('mail.from.address')) {
                    $recipients[] = config('mail.from.address');
                }
            }

            if (! empty($recipients)) {
                app(MailConfigService::class)->apply();
                Mail::to($recipients)->send(
                    new WhatsAppHandoffStaffMail($conversation, $rule, $inboundText)
                );
            }

            // 3. Optional SMS alert
            $staffPhone = $conversation->assignee?->phone ?: settings('company_phone');
            if ($staffPhone) {
                $customer = $conversation->contact?->name ?: ($conversation->profile_name ?: $conversation->wa_id);
                $smsMsg = "Alert: WhatsApp conversation from {$customer} ({$conversation->wa_id}) requires human agent. Open admin inbox.";
                app(SmsService::class)->send($staffPhone, $smsMsg, ['type' => 'whatsapp_handoff']);
            }
        } catch (\Throwable $e) {
            Log::warning("WhatsApp handoff notification failed: " . $e->getMessage());
        }
    }

    /**
     * Trigger a bot reply rule manually on a conversation.
     */
    public function triggerRule(WhatsAppConversation $conversation, WhatsAppAutoReply $rule): array
    {
        if ($rule->is_handoff) {
            $conversation->update(['bot_paused' => true]);
            $this->notifyStaffOfHandoff($conversation, $rule, '[Manual Trigger by Agent]');
        }

        $dispatched = $this->dispatchReply($conversation, $rule);

        return [
            'success' => $dispatched,
            'error' => $dispatched
                ? null
                : ($this->lastError ?: 'Failed to dispatch bot reply (window may be closed or rule invalid).'),
        ];
    }

    public function dispatchReply(WhatsAppConversation $conversation, WhatsAppAutoReply $rule): bool
    {
        $this->lastError = null;

        if ($rule->reply_type === 'template' && $rule->template_name) {
            $components = $this->buildTemplateComponents($rule);
            $result = $this->whatsapp->sendTemplate($conversation, $rule->template_name, $rule->template_language, $components);

            if (! ($result['success'] ?? false)) {
                $this->lastError = $result['error'] ?? 'Template send rejected by WhatsApp.';
                Log::warning("WhatsApp auto-reply template '{$rule->template_name}' (rule #{$rule->id}) failed: " . $this->lastError);
            }

            return (bool) ($result['success'] ?? false);
        }

        // Interactive Quick Reply Buttons (Header + Body + Footer + Buttons)
        $hasButtons = ! empty($rule->buttons) && is_array($rule->buttons) && count(array_filter($rule->buttons)) > 0;
        if (($rule->reply_type === 'buttons' || $hasButtons) && ! empty($rule->reply_text)) {
            $buttons = array_values(array_filter($rule->buttons ?? []));
            if (! empty($buttons)) {
                $result = $this->whatsapp->sendInteractiveButtons(
                    $conversation,
                    $rule->reply_text,
                    $buttons,
                    $rule->header_text,
                    $rule->footer_text,
                    $rule->header_type ?? 'text',
                    $rule->header_image_url
                );

                if ($result['success'] ?? false) {
                    return true;
                }
            }
        }

        // Interactive List Menu
        $hasSections = ! empty($rule->sections) && is_array($rule->sections);
        if (($rule->reply_type === 'list' || $hasSections) && ! empty($rule->reply_text)) {
            $result = $this->whatsapp->sendInteractiveList(
                $conversation,
                $rule->reply_text,
                $rule->list_button_text ?: 'View Options',
                $rule->sections ?? [],
                $rule->header_text,
                $rule->footer_text
            );

            if ($result['success'] ?? false) {
                return true;
            }
        }

        // Image Header without buttons (Send as media message with caption)
        if ($rule->header_type === 'image' && ! empty($rule->header_image_url) && ! empty($rule->reply_text)) {
            $caption = $rule->reply_text;
            if ($rule->footer_text && trim($rule->footer_text) !== '') {
                $caption .= "\n\n_" . trim($rule->footer_text) . '_';
            }

            $result = $this->whatsapp->sendMediaLink($conversation, 'image', $rule->header_image_url, $caption);

            return (bool) ($result['success'] ?? false);
        }

        // Standard or Rich Formatted Text (with optional Header & Footer)
        if ($rule->reply_text) {
            $formatted = $rule->reply_text;
            if ($rule->header_text && trim($rule->header_text) !== '') {
                $formatted = '*' . trim($rule->header_text) . "*\n\n" . $formatted;
            }
            if ($rule->footer_text && trim($rule->footer_text) !== '') {
                $formatted = $formatted . "\n\n_" . trim($rule->footer_text) . '_';
            }

            // Free-form text needs an open 24h window (an inbound just opened it).
            $result = $this->whatsapp->sendText($conversation, $formatted);

            return (bool) ($result['success'] ?? false);
        }

        return false;
    }

    /**
     * Build the Meta template components for an auto-reply rule: an optional
     * media header (document / image / video via public link) honouring the
     * approved template's real header shape, plus ordered body variables.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function buildTemplateComponents(WhatsAppAutoReply $rule): array
    {
        $components = [];

        // Resolve the synced template so we honour its real header type and
        // body-variable count (avoids a guaranteed Meta rejection on mismatch).
        $tpl = WhatsAppTemplate::where('name', $rule->template_name)->first();
        $headerType = $tpl?->getHeaderType(); // 'document' | 'image' | 'video' | 'text' | null

        // Media header: only attach when the template actually declares a media
        // header and the admin supplied a link. For a text/none header we skip it.
        $mediaUrl = trim((string) ($rule->template_header_media_url ?? ''));
        if (in_array($headerType, ['document', 'image', 'video'], true)) {
            if ($mediaUrl === '') {
                // The template requires a media header but no link/file was set —
                // Meta will reject the whole message. Make that diagnosable.
                Log::warning(
                    "WhatsApp auto-reply template '{$rule->template_name}' (rule #{$rule->id}) has a "
                    . "{$headerType} header but no media link/file configured — WhatsApp will reject it. "
                    . 'Add a public HTTPS link or upload a file in the rule.'
                );
            } else {
                $mediaObject = $headerType === 'document'
                    ? ['link' => $mediaUrl, 'filename' => basename(parse_url($mediaUrl, PHP_URL_PATH) ?: 'document.pdf')]
                    : ['link' => $mediaUrl];

                $components[] = [
                    'type' => 'header',
                    'parameters' => [[
                        'type' => $headerType,
                        $headerType => $mediaObject,
                    ]],
                ];
            }
        }

        // Body variables ({{1}}, {{2}}, ...) in order. We pad up to the template's
        // real variable count with a placeholder so a partially-filled (or empty)
        // rule still matches the shape Meta expects instead of being rejected for
        // "number of parameters does not match".
        $expected = (int) ($tpl?->body_variable_count ?? 0);
        $params = array_values($rule->template_params ?? []);

        if ($expected > 0 || ! empty($params)) {
            $count = $expected > 0 ? $expected : count($params);
            $built = [];
            for ($i = 0; $i < $count; $i++) {
                $v = $params[$i] ?? null;
                $built[] = ($v === null || trim((string) $v) === '') ? '—' : (string) $v;
            }

            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => $v], $built),
            ];
        }

        return $components;
    }
}

<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Meta WhatsApp Cloud API webhook. GET performs the verification handshake;
 * POST receives inbound messages and delivery statuses. Requests are
 * authenticated by the X-Hub-Signature-256 HMAC (app secret), and replay is
 * prevented via WebhookEvent (message id idempotency).
 */
class WhatsAppWebhookController extends Controller
{
    public function __construct(private WhatsAppService $whatsapp)
    {
    }

    /** Verification handshake — echo hub.challenge when the verify token matches. */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $expected = config('services.whatsapp.verify_token');

        if ($mode === 'subscribe' && $expected && hash_equals((string) $expected, (string) $token)) {
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request)
    {
        $raw = $request->getContent();

        if (! $this->signatureValid($request, $raw)) {
            Log::warning('WhatsApp webhook: invalid signature');

            return response()->json(['error' => 'invalid signature'], 403);
        }

        $payload = $request->json()->all();

        foreach (data_get($payload, 'entry', []) as $entry) {
            foreach (data_get($entry, 'changes', []) as $change) {
                $value = $change['value'] ?? [];
                $this->handleMessages($value);
                $this->handleStatuses($value);
            }
        }

        // Always 200 quickly so Meta doesn't retry a processed event.
        return response()->json(['ok' => true]);
    }

    protected function handleMessages(array $value): void
    {
        $contacts = collect($value['contacts'] ?? [])->keyBy('wa_id');

        foreach ($value['messages'] ?? [] as $message) {
            $waId = $message['from'] ?? null;
            $waMessageId = $message['id'] ?? null;
            if (! $waId || ! $waMessageId) {
                continue;
            }

            // Idempotency: skip if we've already stored this message id.
            if (WebhookEvent::where('provider', 'whatsapp')->where('event_id', $waMessageId)->exists()) {
                continue;
            }

            $profileName = data_get($contacts->get($waId), 'profile.name');

            try {
                $normalized = $this->normalizeMessage($message);
                $stored = $this->whatsapp->recordInbound($waId, $profileName, $normalized);

                WebhookEvent::create([
                    'provider' => 'whatsapp',
                    'event_type' => 'message',
                    'event_id' => $waMessageId,
                    'payload' => $message,
                    'processed_at' => now(),
                ]);

                // Keyword chatbot FIRST (deterministic rules always win), then
                // the optional AI assistant as a fallback for anything no rule
                // handled. Both are best-effort and can never break the webhook.
                if (! empty($normalized['body'])) {
                    $conversation = $stored->conversation;
                    $handledByRule = false;

                    try {
                        $handledByRule = app(\App\Services\WhatsApp\AutoReplyService::class)
                            ->handle($conversation, $normalized['body']);
                    } catch (\Throwable $e) {
                        Log::warning('WhatsApp auto-reply failed: ' . $e->getMessage());
                    }

                    // AI assistant only when no keyword rule matched and the bot
                    // is not paused (human handoff). Disabled/unconfigured => no-op.
                    if (! $handledByRule && ! $conversation->fresh()->bot_paused) {
                        try {
                            app(\App\Services\WhatsApp\Assistant\TravelAssistantService::class)
                                ->handle($conversation->fresh(), $normalized['body']);
                        } catch (\Throwable $e) {
                            Log::warning('WhatsApp AI assistant failed: ' . $e->getMessage());
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::error('WhatsApp inbound processing failed: ' . $e->getMessage(), ['wa_message_id' => $waMessageId]);
            }
        }
    }

    protected function handleStatuses(array $value): void
    {
        foreach ($value['statuses'] ?? [] as $status) {
            $id = $status['id'] ?? null;
            $state = $status['status'] ?? null;
            if (! $id || ! $state) {
                continue;
            }

            $error = data_get($status, 'errors.0.title');
            $this->whatsapp->recordStatus($id, $state, $error);
        }
    }

    /** Flatten a Cloud API message object into our normalized shape. */
    protected function normalizeMessage(array $message): array
    {
        $type = $message['type'] ?? 'text';
        $body = null;
        $media = null;

        switch ($type) {
            case 'text':
                $body = data_get($message, 'text.body');
                break;
            case 'button':
                $body = data_get($message, 'button.text');
                break;
            case 'interactive':
                $body = data_get($message, 'interactive.button_reply.title')
                    ?? data_get($message, 'interactive.list_reply.title');
                break;
            case 'image':
            case 'video':
            case 'audio':
            case 'document':
            case 'sticker':
                $media = [
                    'id' => data_get($message, "{$type}.id"),
                    'mime' => data_get($message, "{$type}.mime_type"),
                    'filename' => data_get($message, "{$type}.filename"),
                    'caption' => data_get($message, "{$type}.caption"),
                ];
                $body = $media['caption'] ?? null;
                break;
            case 'location':
                $body = 'Location: ' . data_get($message, 'location.latitude') . ',' . data_get($message, 'location.longitude');
                break;
        }

        $ts = $message['timestamp'] ?? null;

        return [
            'wa_message_id' => $message['id'] ?? null,
            'type' => $type,
            'body' => $body,
            'media' => $media,
            'timestamp' => $ts ? \Illuminate\Support\Carbon::createFromTimestamp((int) $ts) : now(),
        ];
    }

    protected function signatureValid(Request $request, string $raw): bool
    {
        $secret = config('services.whatsapp.app_secret');

        // If no app secret is configured, fall back to accepting (dev only).
        if (empty($secret)) {
            return true;
        }

        $header = $request->header('X-Hub-Signature-256', '');
        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $raw, $secret);

        return hash_equals($expected, $header);
    }
}

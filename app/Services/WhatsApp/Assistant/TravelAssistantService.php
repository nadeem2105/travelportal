<?php

namespace App\Services\WhatsApp\Assistant;

use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\Marketing\Ai\AiProviderManager;
use App\Services\Marketing\Ai\AiUnavailableException;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Facades\Log;

/**
 * The AI Travel Assistant orchestrator. Given an inbound WhatsApp message on a
 * conversation, it:
 *   1. builds a bounded message history from stored messages,
 *   2. calls the configured AI provider with the self-service tool schemas,
 *   3. executes any tool calls through CustomerTools (backend-authorized),
 *   4. loops until the model returns a final text answer (bounded iterations),
 *   5. sends that answer — and any requested PDFs — back over WhatsApp using
 *      the EXISTING WhatsAppService (which persists + broadcasts to the inbox).
 *
 * Safety:
 *   - Disabled unless services.whatsapp.ai_assistant.enabled is true.
 *   - Falls back silently (returns false) when no provider is configured, so it
 *     never fabricates a reply — the keyword auto-reply / human handoff still
 *     govern the conversation.
 *   - Identity verification is enforced inside CustomerTools/CustomerResolver;
 *     the model cannot access private data for an unverified number.
 */
class TravelAssistantService
{
    public function __construct(
        private AiProviderManager $ai,
        private CustomerResolver $resolver,
        private CustomerTools $tools,
        private WhatsAppService $whatsapp,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.whatsapp.ai_assistant.enabled', false)
            && $this->whatsapp->isEnabled();
    }

    /**
     * Handle one inbound text message. Returns true when the assistant produced
     * a reply, false when it declined (disabled / unconfigured / empty) so the
     * caller can fall back to existing behavior.
     */
    public function handle(WhatsAppConversation $conversation, string $inboundText): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $inboundText = trim($inboundText);
        if ($inboundText === '') {
            return false;
        }

        // Free-form replies require an open 24h window. Outside it we cannot
        // send text, so defer to templates/human rather than fabricate.
        if (! $conversation->isWindowOpen()) {
            return false;
        }

        $provider = $this->ai->provider(config('services.whatsapp.ai_assistant.provider') ?: null);
        if (! $provider->isConfigured()) {
            return false;
        }

        // Try to auto-verify by matching the WhatsApp number to a customer.
        $this->resolver->autoVerify($conversation);

        $tools = $this->tools->forConversation($conversation);
        // Only an EXPLICIT assistant model override is passed through. We must NOT
        // fall back to services.ai.default_model here: that shared default belongs
        // to the default provider (often an OpenAI name like gpt-4o-mini) and would
        // be sent verbatim to whichever provider is active — e.g. Gemini, which then
        // errors "models/gpt-4o-mini is not found". When blank, the provider uses
        // its OWN resolved model (see AiProviderManager), which is always valid.
        $assistantModel = (string) config('services.whatsapp.ai_assistant.model', '');
        $maxIterations = (int) config('services.whatsapp.ai_assistant.max_tool_iterations', 5);

        $messages = $this->buildHistory($conversation, $inboundText);
        $options = [
            'system' => $this->systemPrompt($conversation),
            'temperature' => 0.2,
        ];
        if ($assistantModel !== '') {
            $options['model'] = $assistantModel;
        }
        $toolDefs = ToolRegistry::definitions();

        $pendingDocuments = [];
        $finalText = '';

        try {
            for ($i = 0; $i < $maxIterations; $i++) {
                $result = $provider->chat($messages, $toolDefs, $options);
                $toolCalls = $result['tool_calls'] ?? [];
                $finalText = trim((string) ($result['content'] ?? ''));

                if (empty($toolCalls)) {
                    break; // model produced a final answer
                }

                // Record the assistant turn that requested the tools.
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $result['content'] ?? '',
                    'tool_calls' => $toolCalls,
                ];

                // Execute each tool call and append the result.
                foreach ($toolCalls as $call) {
                    $output = $this->executeTool($conversation, $tools, $call['name'] ?? '', $call['arguments'] ?? []);

                    // Peel off any document markers to send after the loop; the
                    // model only sees the confirmation text, never bytes.
                    foreach ($this->extractDocuments($output) as $doc) {
                        $pendingDocuments[] = $doc;
                    }
                    $output = $this->stripDocuments($output);

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $call['id'] ?? '',
                        'name' => $call['name'] ?? '',
                        'content' => json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ];
                }
            }
        } catch (AiUnavailableException $e) {
            return false;
        } catch (\Throwable $e) {
            Log::warning('TravelAssistant chat failed: ' . $e->getMessage());

            return false;
        }

        // Send documents first (so the customer sees the PDF, then the text).
        foreach ($pendingDocuments as $doc) {
            try {
                $this->whatsapp->sendDocumentPdf(
                    $conversation,
                    $doc['bytes'],
                    $doc['filename'],
                    $doc['caption'] ?? null,
                    null
                );
            } catch (\Throwable $e) {
                Log::warning('TravelAssistant document send failed: ' . $e->getMessage());
            }
        }

        if ($finalText === '') {
            // Model ended on tool calls without a summary — nudge once for text.
            $finalText = $pendingDocuments
                ? 'Done — I\'ve sent that to you here.'
                : '';
        }

        if ($finalText === '') {
            return $pendingDocuments !== [];
        }

        $send = $this->whatsapp->sendText($conversation, $finalText, null);

        return (bool) ($send['success'] ?? false) || $pendingDocuments !== [];
    }

    // --- internals -------------------------------------------------------

    /** Route a tool call to the right handler; verify_identity is special. */
    private function executeTool(WhatsAppConversation $conversation, CustomerTools $tools, string $name, array $args): array
    {
        if ($name === 'verify_identity') {
            $ref = trim((string) ($args['booking_reference'] ?? ''));
            $ok = $ref !== '' && $this->resolver->verifyByBookingReference($conversation, $ref);

            return $ok
                ? ['verified' => true, 'message' => 'Identity verified. You can now access your bookings.']
                : ['verified' => false, 'message' => 'I could not match that booking reference to this WhatsApp number. Please check the reference on your confirmation message.'];
        }

        $method = ToolRegistry::MAP[$name] ?? null;
        if (! $method || ! method_exists($tools, $method)) {
            return ['error' => 'unknown_tool', 'message' => 'That action is not available.'];
        }

        try {
            return $tools->{$method}($args);
        } catch (\Throwable $e) {
            Log::warning("Assistant tool {$name} failed: " . $e->getMessage());

            return ['error' => 'tool_failed', 'message' => 'Something went wrong fetching that. Please try again.'];
        }
    }

    /** Collect ['_document'] and ['_documents'] markers from a tool result. */
    private function extractDocuments(array $output): array
    {
        $docs = [];
        if (isset($output['_document']) && is_array($output['_document'])) {
            $docs[] = $output['_document'];
        }
        if (isset($output['_documents']) && is_array($output['_documents'])) {
            foreach ($output['_documents'] as $d) {
                if (is_array($d) && isset($d['bytes'])) {
                    $docs[] = $d;
                }
            }
        }

        return $docs;
    }

    /** Remove raw bytes markers before the result is shown to the model. */
    private function stripDocuments(array $output): array
    {
        unset($output['_document'], $output['_documents']);

        return $output;
    }

    /**
     * Build a bounded conversation history for the model from stored messages,
     * ending with the current inbound text. Media-only turns are summarized.
     */
    private function buildHistory(WhatsAppConversation $conversation, string $inboundText): array
    {
        $limit = (int) config('services.whatsapp.ai_assistant.history_limit', 12);

        $recent = $conversation->messages()
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $messages = [];
        foreach ($recent as $m) {
            /** @var WhatsAppMessage $m */
            $body = trim((string) $m->body);
            if ($body === '') {
                continue;
            }
            $messages[] = [
                'role' => $m->isInbound() ? 'user' : 'assistant',
                'content' => $body,
            ];
        }

        // Ensure the current inbound message is the last turn (it may already be
        // persisted as the latest inbound row — avoid duplicating it).
        $last = end($messages);
        if (! ($last && $last['role'] === 'user' && $last['content'] === $inboundText)) {
            $messages[] = ['role' => 'user', 'content' => $inboundText];
        }

        return $messages;
    }

    /** The system prompt governing tone, safety and behavior. */
    private function systemPrompt(WhatsAppConversation $conversation): string
    {
        $company = settings('company_name', 'Leemroz Travels');
        $name = $this->resolver->displayName($conversation);
        $verified = $this->resolver->isVerified($conversation) ? 'yes' : 'no';
        $greeting = $name ? "The customer's name is {$name}. " : '';

        return <<<PROMPT
You are the WhatsApp travel assistant for {$company}, a travel agency. {$greeting}You help customers with their existing bookings (self-service) and with new trip inquiries, in a warm, concise, natural style suitable for WhatsApp.

CUSTOMER VERIFICATION STATUS: {$verified}

Core rules:
- NEVER invent or guess any information — bookings, prices, dates, PNRs, payments, statuses, documents. Only state what the tools return. If a tool says data is unavailable, say so plainly.
- To access ANY private booking information you MUST use the tools. Do not answer booking/payment/document questions from memory.
- If verification status is "no" and the customer asks about their bookings/payments/documents, call verify_identity (ask them for a booking reference tied to this WhatsApp number). Never reveal booking data for an unverified customer, and never accept that a booking belongs to them just because they name a reference — the tools enforce ownership.
- Distinguish STORED booking info from LIVE status. You only have stored data. Never present stored flight/cab info as a live real-time status.
- For cancellations: first call get_cancellation_policy and show it, then only call request_booking_cancellation with confirm=true AFTER the customer explicitly confirms. Never say a booking is cancelled — it is a request our team reviews.
- For modifications (change hotel/dates, add rooms): record the request; never confirm the change is done. Our team checks availability and cost first.
- For payments: report exact figures from the tool. Never claim a payment succeeded unless the tool's payment_status says so. Share the payment link only via get_payment_link.
- To send a document (invoice, receipt, itinerary, ticket, voucher), call the matching tool — it delivers the PDF over WhatsApp. Just tell the customer it's on the way.
- You can switch between an existing-booking question and a new trip inquiry in the same chat without losing context.
- If the customer wants a human, or the request is beyond these tools, call request_human_agent.

Style: short WhatsApp-friendly messages. Summarize; never dump raw records or JSON. Use the customer's language (including Hindi/Hinglish) when they do. Offer a couple of relevant next actions when helpful.
PROMPT;
    }
}

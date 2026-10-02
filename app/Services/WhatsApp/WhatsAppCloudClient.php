<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin transport wrapper around the Meta WhatsApp Cloud API (Graph). Knows how
 * to POST messages and returns a normalized result — it does NOT persist
 * anything (that's WhatsAppService's job). Config comes from config/services.php.
 */
class WhatsAppCloudClient
{
    public function isConfigured(): bool
    {
        $c = config('services.whatsapp');

        return ! empty($c['access_token']) && ! empty($c['phone_number_id']);
    }

    protected function baseUrl(): string
    {
        $version = config('services.whatsapp.api_version', 'v21.0');
        $phoneId = config('services.whatsapp.phone_number_id');

        return "https://graph.facebook.com/{$version}/{$phoneId}";
    }

    /** Send a free-form text message (only valid inside the 24h customer window). */
    public function sendText(string $to, string $body, bool $previewUrl = false): array
    {
        return $this->post([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => ['preview_url' => $previewUrl, 'body' => $body],
        ]);
    }

    /**
     * Send an interactive button message (up to 3 quick reply buttons).
     *
     * @param  array<int, string|array{id: string, title: string}>  $buttons
     */
    public function sendInteractiveButtons(string $to, string $body, array $buttons, ?string $header = null, ?string $footer = null, ?string $headerType = 'text', ?string $headerImageUrl = null): array
    {
        $actionButtons = [];
        foreach (array_slice(array_values($buttons), 0, 3) as $i => $btn) {
            $title = is_array($btn) ? ($btn['title'] ?? '') : (string) $btn;
            $id = is_array($btn) ? ($btn['id'] ?? ('btn_' . ($i + 1))) : ('btn_' . ($i + 1));
            $title = trim($title);
            if ($title === '') {
                continue;
            }
            $actionButtons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => mb_substr((string) $id, 0, 256),
                    'title' => mb_substr($title, 0, 20),
                ],
            ];
        }

        $interactive = [
            'type' => 'button',
            'body' => ['text' => $body],
            'action' => ['buttons' => $actionButtons],
        ];

        if ($headerType === 'image' && $headerImageUrl && trim($headerImageUrl) !== '') {
            $interactive['header'] = [
                'type' => 'image',
                'image' => ['link' => trim($headerImageUrl)],
            ];
        } elseif ($header && trim($header) !== '') {
            $interactive['header'] = ['type' => 'text', 'text' => mb_substr(trim($header), 0, 60)];
        }

        if ($footer && trim($footer) !== '') {
            $interactive['footer'] = ['text' => mb_substr(trim($footer), 0, 60)];
        }

        return $this->post([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => $interactive,
        ]);
    }

    /**
     * Send an interactive list menu message (up to 10 options across sections).
     *
     * @param  array<int, array{title: string, rows: array<int, array{id: string, title: string, description?: string}>}>  $sections
     */
    public function sendInteractiveList(string $to, string $body, string $buttonLabel, array $sections, ?string $header = null, ?string $footer = null): array
    {
        $interactive = [
            'type' => 'list',
            'body' => ['text' => $body],
            'action' => [
                'button' => mb_substr(trim($buttonLabel) ?: 'Select Option', 0, 20),
                'sections' => $sections,
            ],
        ];

        if ($header && trim($header) !== '') {
            $interactive['header'] = ['type' => 'text', 'text' => mb_substr(trim($header), 0, 60)];
        }
        if ($footer && trim($footer) !== '') {
            $interactive['footer'] = ['text' => mb_substr(trim($footer), 0, 60)];
        }

        return $this->post([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => $interactive,
        ]);
    }

    /**
     * Send an approved template message (valid anytime — used to (re)open a window).
     *
     * @param  array  $components  Optional Cloud API template components (body params, etc.)
     */
    public function sendTemplate(string $to, string $template, ?string $lang = null, array $components = []): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $lang ?: config('services.whatsapp.default_template_lang', 'en_US')],
            ],
        ];
        if ($components) {
            $payload['template']['components'] = $components;
        }

        return $this->post($payload);
    }

    /** Send a media message by public link (image, document, etc.). */
    public function sendMedia(string $to, string $type, string $link, ?string $caption = null): array
    {
        $media = ['link' => $link];
        if ($caption && in_array($type, ['image', 'video', 'document'], true)) {
            $media['caption'] = $caption;
        }

        return $this->post([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => $type,
            $type => $media,
        ]);
    }

    /**
     * Fetch message templates from the WhatsApp Business account (paginated).
     *
     * @return array{success:bool, data?:array, error?:string}
     */
    public function fetchTemplates(): array
    {
        $token = config('services.whatsapp.access_token');
        $waba = config('services.whatsapp.waba_id');
        $version = config('services.whatsapp.api_version', 'v21.0');

        if (empty($token) || empty($waba)) {
            return ['success' => false, 'error' => 'WhatsApp WABA id / access token not configured.'];
        }

        $all = [];
        $url = "https://graph.facebook.com/{$version}/{$waba}/message_templates";
        $params = ['limit' => 100, 'fields' => 'id,name,language,category,status,components'];

        try {
            // Follow paging.next up to a sane cap.
            for ($page = 0; $page < 20 && $url; $page++) {
                $response = Http::withToken($token)->acceptJson()->timeout(20)->get($url, $params);
                $params = []; // subsequent pages carry their own query in next url

                if (! $response->successful()) {
                    $json = $response->json() ?? [];

                    return ['success' => false, 'error' => $json['error']['message'] ?? ('HTTP ' . $response->status())];
                }

                $json = $response->json();
                $all = array_merge($all, $json['data'] ?? []);
                $url = $json['paging']['next'] ?? null;
            }

            return ['success' => true, 'data' => $all];
        } catch (\Throwable $e) {
            Log::error('WhatsApp template fetch error: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verify the credentials by fetching the configured phone number's profile.
     *
     * @return array{success:bool, data?:array, error?:string}
     */
    public function verifyConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Access token or phone number ID is missing.'];
        }

        $version = config('services.whatsapp.api_version', 'v21.0');
        $phoneId = config('services.whatsapp.phone_number_id');

        try {
            $response = Http::withToken(config('services.whatsapp.access_token'))
                ->acceptJson()->timeout(15)
                ->get("https://graph.facebook.com/{$version}/{$phoneId}", [
                    'fields' => 'verified_name,display_phone_number,quality_rating,code_verification_status',
                ]);

            $json = $response->json() ?? [];
            if ($response->successful()) {
                return ['success' => true, 'data' => $json];
            }

            return ['success' => false, 'error' => $json['error']['message'] ?? ('HTTP ' . $response->status())];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create (submit for approval) a message template on the WhatsApp Business
     * account. Returns the Meta template id + initial status on success.
     *
     * @param  array  $components  Cloud API components (BODY, optional HEADER/BUTTONS with examples)
     * @return array{success:bool, id?:string, status?:string, error?:string, raw?:array}
     */
    public function createTemplate(string $name, string $category, string $language, array $components): array
    {
        $token = config('services.whatsapp.access_token');
        $waba = config('services.whatsapp.waba_id');
        $version = config('services.whatsapp.api_version', 'v21.0');

        if (empty($token) || empty($waba)) {
            return ['success' => false, 'error' => 'WhatsApp WABA id / access token not configured.'];
        }

        try {
            $response = Http::withToken($token)->acceptJson()->timeout(20)
                ->post("https://graph.facebook.com/{$version}/{$waba}/message_templates", [
                    'name' => $name,
                    'category' => $category,
                    'language' => $language,
                    'components' => $components,
                ]);

            $json = $response->json() ?? [];
            if ($response->successful()) {
                return ['success' => true, 'id' => $json['id'] ?? null, 'status' => $json['status'] ?? 'PENDING', 'raw' => $json];
            }

            return ['success' => false, 'error' => $json['error']['message'] ?? ('HTTP ' . $response->status()), 'raw' => $json];
        } catch (\Throwable $e) {
            Log::error('WhatsApp template create error: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Upload media (e.g. a PDF) to WhatsApp and get a reusable media id.
     *
     * @return array{success:bool, id?:string, error?:string}
     */
    public function uploadMedia(string $binary, string $mime, string $filename): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'WhatsApp is not configured.'];
        }

        try {
            $response = Http::withToken(config('services.whatsapp.access_token'))
                ->timeout(30)
                ->attach('file', $binary, $filename, ['Content-Type' => $mime])
                ->post($this->baseUrl() . '/media', [
                    'messaging_product' => 'whatsapp',
                    'type' => $mime,
                ]);

            $json = $response->json() ?? [];
            if ($response->successful() && ! empty($json['id'])) {
                return ['success' => true, 'id' => $json['id']];
            }

            return ['success' => false, 'error' => $json['error']['message'] ?? ('HTTP ' . $response->status())];
        } catch (\Throwable $e) {
            Log::error('WhatsApp media upload error: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** Send a document message by uploaded media id. */
    public function sendDocument(string $to, string $mediaId, string $filename, ?string $caption = null): array
    {
        $doc = ['id' => $mediaId, 'filename' => $filename];
        if ($caption) {
            $doc['caption'] = $caption;
        }

        return $this->post([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'document',
            'document' => $doc,
        ]);
    }

    /**
     * Resumable upload (App-level) to obtain a media HANDLE for use as a template
     * header example. Two steps: create the upload session, then upload the bytes.
     *
     * @return array{success:bool, handle?:string, error?:string}
     */
    public function uploadResumable(string $binary, string $mime, string $filename): array
    {
        $token = config('services.whatsapp.access_token');
        $appId = config('services.whatsapp.app_id');
        $version = config('services.whatsapp.api_version', 'v21.0');

        if (empty($token) || empty($appId)) {
            return ['success' => false, 'error' => 'WhatsApp app id / access token not configured.'];
        }

        try {
            // 1) Create the upload session.
            $session = Http::withToken($token)->acceptJson()->timeout(20)
                ->post("https://graph.facebook.com/{$version}/{$appId}/uploads", [
                    'file_name' => $filename,
                    'file_length' => strlen($binary),
                    'file_type' => $mime,
                ]);

            $sid = $session->json('id');
            if (! $session->successful() || empty($sid)) {
                return ['success' => false, 'error' => $session->json('error.message') ?? 'Could not start upload session.'];
            }

            // 2) Upload the bytes (OAuth auth header + file_offset 0).
            $upload = Http::withHeaders([
                'Authorization' => 'OAuth ' . $token,
                'file_offset' => '0',
            ])->withBody($binary, $mime)->timeout(40)
                ->post("https://graph.facebook.com/{$version}/{$sid}");

            $handle = $upload->json('h');
            if (! $upload->successful() || empty($handle)) {
                return ['success' => false, 'error' => $upload->json('error.message') ?? 'Upload failed.'];
            }

            return ['success' => true, 'handle' => $handle];
        } catch (\Throwable $e) {
            Log::error('WhatsApp resumable upload error: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** Mark an inbound message as read (blue ticks). Best-effort. */
    public function markRead(string $messageId): array
    {
        return $this->post([
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
        ]);
    }

    /**
     * @return array{success:bool, wa_message_id?:string, error?:string, status?:int, raw?:array}
     */
    protected function post(array $payload): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'WhatsApp is not configured.'];
        }

        try {
            $response = Http::withToken(config('services.whatsapp.access_token'))
                ->acceptJson()
                ->timeout(20)
                ->post($this->baseUrl() . '/messages', $payload);

            $json = $response->json() ?? [];

            if ($response->successful()) {
                return [
                    'success' => true,
                    'wa_message_id' => $json['messages'][0]['id'] ?? null,
                    'raw' => $json,
                ];
            }

            $error = $json['error']['message'] ?? ('HTTP ' . $response->status());
            Log::warning('WhatsApp send failed', ['status' => $response->status(), 'error' => $json['error'] ?? null]);

            return ['success' => false, 'error' => $error, 'status' => $response->status(), 'raw' => $json];
        } catch (\Throwable $e) {
            Log::error('WhatsApp transport error: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

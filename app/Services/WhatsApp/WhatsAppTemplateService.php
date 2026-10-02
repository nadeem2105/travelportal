<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppTemplate;

/**
 * Syncs message templates from Meta into the local whatsapp_templates cache and
 * provides helpers to inspect a template's body variable count (so campaigns
 * know how many parameters to collect).
 */
class WhatsAppTemplateService
{
    public function __construct(private WhatsAppCloudClient $client)
    {
    }

    /**
     * Pull templates from Meta and upsert them locally.
     *
     * @return array{success:bool, synced?:int, error?:string}
     */
    public function sync(): array
    {
        $result = $this->client->fetchTemplates();
        if (! $result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'Fetch failed'];
        }

        $count = 0;
        foreach ($result['data'] as $tpl) {
            $components = $tpl['components'] ?? [];
            $body = collect($components)->firstWhere('type', 'BODY');
            $bodyText = $body['text'] ?? null;

            WhatsAppTemplate::updateOrCreate(
                ['name' => $tpl['name'], 'language' => $tpl['language'] ?? 'en_US'],
                [
                    'meta_id' => $tpl['id'] ?? null,
                    'category' => $tpl['category'] ?? null,
                    'status' => $tpl['status'] ?? 'APPROVED',
                    'components' => $components,
                    'body_variable_count' => $this->countBodyVariables($bodyText),
                    'body_preview' => $bodyText,
                    'synced_at' => now(),
                ]
            );
            $count++;
        }

        return ['success' => true, 'synced' => $count];
    }

    /**
     * Create + submit a template to Meta and mirror it locally as PENDING.
     *
     * @param  array<int,string>  $examples  Sample value per {{n}} variable (in order)
     * @return array{success:bool, error?:string, template?:\App\Models\WhatsAppTemplate}
     */
    public function createTemplate(string $name, string $category, string $language, string $bodyText, array $examples = [], string $headerType = 'none'): array
    {
        $name = strtolower(trim($name));

        // Meta structural rules — catch them here with a clear message instead of
        // a generic "Invalid parameter" from the Graph API.
        if ($structural = $this->bodyStructureError($bodyText)) {
            return ['success' => false, 'error' => $structural];
        }

        $varCount = $this->countBodyVariables($bodyText);

        // Meta requires an example value for every body variable. Keep provided
        // samples in order and auto-fill any that are blank so submission never
        // fails just because a sample box was left empty.
        if ($varCount > 0) {
            $clean = [];
            foreach (array_values($examples) as $v) {
                $clean[] = (is_string($v) && trim($v) !== '') ? trim($v) : null;
            }
            $filled = [];
            for ($i = 0; $i < $varCount; $i++) {
                $filled[] = $clean[$i] ?? ('Sample ' . ($i + 1));
            }
            $examples = $filled;
        }

        $components = [];

        // Optional document header — requires a sample media HANDLE from the
        // resumable upload API, which Meta uses during review.
        if ($headerType === 'document') {
            $handleResult = $this->sampleDocumentHandle();
            if (! $handleResult['success']) {
                return ['success' => false, 'error' => 'Could not prepare the document header sample: ' . ($handleResult['error'] ?? 'unknown')];
            }
            $components[] = [
                'type' => 'HEADER',
                'format' => 'DOCUMENT',
                'example' => ['header_handle' => [$handleResult['handle']]],
            ];
        }

        $body = ['type' => 'BODY', 'text' => $bodyText];
        if ($varCount > 0) {
            $body['example'] = ['body_text' => [$examples]];
        }
        $components[] = $body;

        $result = $this->client->createTemplate($name, strtoupper($category), $language, $components);
        if (! $result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'Submission failed'];
        }

        $template = \App\Models\WhatsAppTemplate::updateOrCreate(
            ['name' => $name, 'language' => $language],
            [
                'meta_id' => $result['id'] ?? null,
                'category' => strtoupper($category),
                'status' => $result['status'] ?? 'PENDING',
                'components' => $components,
                'body_variable_count' => $varCount,
                'body_preview' => $bodyText,
                'synced_at' => now(),
            ]
        );

        return ['success' => true, 'template' => $template];
    }

    /**
     * Generate a small sample PDF and upload it to get a header handle for a
     * document-header template.
     *
     * @return array{success:bool, handle?:string, error?:string}
     */
    protected function sampleDocumentHandle(): array
    {
        try {
            $level = ob_get_level();
            try {
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML(
                    '<html><body style="font-family:sans-serif;padding:40px"><h1>Sample Document</h1><p>This is a sample document used for WhatsApp template approval.</p></body></html>'
                )->setPaper('a4', 'portrait')->output();
            } finally {
                while (ob_get_level() > $level) {
                    ob_end_clean();
                }
            }

            return $this->client->uploadResumable($pdf, 'application/pdf', 'sample.pdf');
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Returns a human-readable error if the body violates Meta's structural rules
     * (variable at start/end, or two variables back-to-back), else null.
     */
    public function bodyStructureError(string $bodyText): ?string
    {
        $trimmed = trim($bodyText);

        if (preg_match('/^\s*\{\{\s*\d+\s*\}\}/', $trimmed)) {
            return 'The message body cannot start with a variable. Add some text before it.';
        }
        if (preg_match('/\{\{\s*\d+\s*\}\}\s*$/', $trimmed)) {
            return 'The message body cannot end with a variable. Add some text after it.';
        }
        // Two variables separated only by whitespace.
        if (preg_match('/\{\{\s*\d+\s*\}\}\s*\{\{\s*\d+\s*\}\}/', $trimmed)) {
            return 'Two variables cannot be placed back-to-back. Add text between them.';
        }

        return null;
    }

    /** Count distinct {{n}} placeholders in a template body. */
    public function countBodyVariables(?string $bodyText): int
    {
        if (! $bodyText) {
            return 0;
        }
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $bodyText, $m);

        return empty($m[1]) ? 0 : (int) max($m[1]);
    }
}

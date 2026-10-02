<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppAutoReply extends Model
{
    protected $table = 'whatsapp_auto_replies';

    protected $fillable = [
        'name', 'match_type', 'keywords', 'reply_type', 'reply_text',
        'header_type', 'header_text', 'header_image_url', 'footer_text',
        'buttons', 'list_button_text', 'sections',
        'template_name', 'template_language', 'template_params', 'template_header_media_url',
        'is_default', 'is_handoff',
        'active', 'priority', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'buttons' => 'array',
            'sections' => 'array',
            'template_params' => 'array',
            'is_default' => 'boolean',
            'is_handoff' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /** Does this rule match the given (already lowercased) inbound text? */
    public function matches(string $text): bool
    {
        if ($this->is_default) {
            return false; // defaults are only used as a fallback, never a direct match
        }

        foreach ($this->keywords ?? [] as $keyword) {
            $kw = mb_strtolower(trim((string) $keyword));
            if ($kw === '') {
                continue;
            }

            $hit = match ($this->match_type) {
                'exact' => $text === $kw,
                'starts_with' => str_starts_with($text, $kw),
                default => str_contains($text, $kw), // contains
            };

            if ($hit) {
                return true;
            }
        }

        return false;
    }
}

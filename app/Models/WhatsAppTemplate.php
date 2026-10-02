<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplate extends Model
{
    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'meta_id', 'name', 'language', 'category', 'status',
        'components', 'body_variable_count', 'body_preview', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'components' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function isApproved(): bool
    {
        return strtoupper((string) $this->status) === 'APPROVED';
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'APPROVED');
    }

    public function getHeaderComponent(): ?array
    {
        return collect($this->components ?? [])->firstWhere('type', 'HEADER');
    }

    public function getHeaderType(): ?string
    {
        $header = $this->getHeaderComponent();
        if (! $header) {
            return null;
        }

        return strtolower($header['format'] ?? 'text');
    }

    public function getHeaderFormat(): ?string
    {
        $header = $this->getHeaderComponent();

        return $header['format'] ?? null;
    }

    public function getBodyText(): ?string
    {
        $body = collect($this->components ?? [])->firstWhere('type', 'BODY');

        return $body['text'] ?? $this->body_preview;
    }

    public function getBodyVariables(): array
    {
        $body = $this->getBodyText() ?? '';
        preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);
        $indices = array_unique($matches[1] ?? []);
        sort($indices, SORT_NUMERIC);

        $examples = [];
        $bodyComp = collect($this->components ?? [])->firstWhere('type', 'BODY');
        if (! empty($bodyComp['example']['body_text'][0])) {
            $examples = $bodyComp['example']['body_text'][0];
        }

        $vars = [];
        foreach ($indices as $i => $num) {
            $vars[] = [
                'index' => (int) $num,
                'placeholder' => "{{{$num}}}",
                'example' => $examples[$i] ?? ('Sample ' . $num),
            ];
        }

        return $vars;
    }
}

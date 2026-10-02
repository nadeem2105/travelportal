<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $fillable = ['key', 'channel', 'name', 'subject', 'body', 'variables', 'is_active'];

    protected function casts(): array
    {
        return ['variables' => 'array', 'is_active' => 'boolean'];
    }

    public function render(array $variables): array
    {
        $subject = $this->subject ?? '';
        $body = $this->body;

        foreach ($variables as $key => $value) {
            $subject = str_replace('{' . $key . '}', (string) $value, $subject);
            $body = str_replace('{' . $key . '}', (string) $value, $body);
        }

        return ['subject' => $subject, 'body' => $body];
    }
}

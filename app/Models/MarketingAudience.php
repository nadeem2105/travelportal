<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingAudience extends Model
{
    protected $table = 'marketing_audiences';

    protected $fillable = [
        'account_id',
        'provider',
        'external_id',
        'name',
        'type',
        'definition',
    ];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(MarketingAccount::class, 'account_id');
    }
}

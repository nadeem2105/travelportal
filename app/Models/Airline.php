<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Airline extends Model
{
    protected $fillable = [
        'code', 'name', 'logo', 'is_lcc', 'default_baggage_kg', 'status',
    ];

    protected function casts(): array
    {
        return ['is_lcc' => 'boolean'];
    }
}

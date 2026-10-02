<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlightSearch extends Model
{
    protected $fillable = ['user_id', 'params', 'results_count', 'supplier_id'];

    protected function casts(): array
    {
        return ['params' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

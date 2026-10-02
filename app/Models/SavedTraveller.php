<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedTraveller extends Model
{
    protected $fillable = [
        'user_id', 'title', 'first_name', 'last_name', 'dob', 'gender', 'id_type', 'id_number',
    ];

    protected function casts(): array
    {
        return ['dob' => 'date'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    protected $fillable = ['user_id', 'wishlistable_type', 'wishlistable_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wishlistable()
    {
        return $this->morphTo();
    }
}

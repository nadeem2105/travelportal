<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CabLocation extends Model
{
    protected $fillable = ['name', 'city', 'type', 'sort_order', 'status'];
}

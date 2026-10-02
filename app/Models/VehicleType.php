<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleType extends Model
{
    protected $fillable = ['name', 'description', 'icon', 'sort_order', 'status'];

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CabVendor extends Model
{
    protected $fillable = [
        'name', 'contact_person', 'phone', 'email', 'commission_percent', 'status',
    ];

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'vendor_id');
    }
}

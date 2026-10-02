<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverAssignmentHistory extends Model
{
    protected $fillable = [
        'driver_assignment_id', 'from_driver_id', 'to_driver_id', 'reason', 'changed_by',
    ];

    public function assignment()
    {
        return $this->belongsTo(DriverAssignment::class, 'driver_assignment_id');
    }

    public function fromDriver()
    {
        return $this->belongsTo(Driver::class, 'from_driver_id');
    }

    public function toDriver()
    {
        return $this->belongsTo(Driver::class, 'to_driver_id');
    }
}

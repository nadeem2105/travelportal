<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierCredential extends Model
{
    protected $fillable = ['supplier_id', 'environment', 'credentials', 'is_active'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $guard = 'admin';

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'avatar',
        'is_super_admin', 'status', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'admin_role');
    }

    public function hasRole($slug): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        return $this->roles->contains('slug', $slug);
    }

    /**
     * Server-side permission check. Super admin bypasses everything.
     */
    public function can($permission, $arguments = []): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        $permissions = $this->permissions();

        if (is_string($permission)) {
            return $permissions->contains('slug', $permission);
        }

        return parent::can($permission, $arguments);
    }

    public function permissions()
    {
        return $this->roles->flatMap->permissions->unique('id');
    }

    public function logs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    protected static function booted(): void
    {
        static::deleting(function ($admin) {
            $admin->roles()->detach();
        });
    }
}

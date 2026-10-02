<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $all = Permission::pluck('id')->toArray();

        $roles = [
            'Super Admin' => null, // gets every permission
            'Admin' => $all,
            'Booking Manager' => ['view_bookings', 'create_booking', 'edit_booking', 'cancel_booking', 'manage_customers'],
            'Flight Manager' => ['manage_flights', 'view_bookings', 'manage_suppliers'],
            'Hotel Manager' => ['manage_hotels', 'view_bookings'],
            'Package Manager' => ['manage_packages', 'manage_content'],
            'Finance' => ['view_bookings', 'manage_payments', 'manage_refunds', 'manage_pricing', 'manage_reports', 'view_analytics'],
            'Support' => ['view_bookings', 'manage_customers'],
            'Content Manager' => ['manage_content'],
            'Marketing' => ['manage_content', 'manage_pricing', 'manage_reports', 'view_analytics'],
        ];

        foreach ($roles as $name => $slugs) {
            $role = Role::updateOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($name)],
                ['name' => $name, 'is_system' => true]
            );

            $role->permissions()->sync(
                $slugs === null ? $all : Permission::whereIn('slug', $slugs)->pluck('id')->toArray()
            );
        }
    }
}

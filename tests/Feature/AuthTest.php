<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_customer_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test Traveller',
            'email' => 'traveller@example.com',
            'password' => 'Secret@123',
            'password_confirmation' => 'Secret@123',
        ]);

        $response->assertRedirect('/account');
        $this->assertDatabaseHas('users', ['email' => 'traveller@example.com']);
    }

    public function test_customer_can_login(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/account');
    }

    public function test_customer_login_rejects_bad_credentials(): void
    {
        User::factory()->create(['email' => 'a@b.com']);

        $this->post('/login', ['email' => 'a@b.com', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
    }

    public function test_admin_can_login(): void
    {
        Admin::factory()->create(['email' => 'admin@test.com', 'password' => 'Password@1']);

        $this->post('/admin/login', ['email' => 'admin@test.com', 'password' => 'Password@1'])
            ->assertRedirect('/admin');
    }

    public function test_inactive_admin_cannot_login(): void
    {
        Admin::factory()->create(['email' => 'blocked@test.com', 'password' => 'Password@1', 'status' => 'inactive']);

        $this->post('/admin/login', ['email' => 'blocked@test.com', 'password' => 'Password@1'])
            ->assertSessionHasErrors('email');
    }

    public function test_admin_permissions_gate_access(): void
    {
        $role = Role::create(['name' => 'Viewer', 'slug' => 'viewer']);
        $role->permissions()->sync(Permission::whereIn('slug', ['view_bookings'])->pluck('id'));

        $admin = Admin::factory()->create();
        $admin->roles()->sync([$role->id]);

        $this->actingAs($admin, 'admin')->get('/admin/bookings')->assertOk();
        $this->actingAs($admin, 'admin')->get('/admin/settings')->assertForbidden();

        // super admin passes everything
        $super = Admin::factory()->create(['is_super_admin' => true]);
        $this->actingAs($super, 'admin')->get('/admin/settings')->assertOk();
    }
}

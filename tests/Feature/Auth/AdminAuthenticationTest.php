<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_screen_can_be_rendered(): void
    {
        $this->withoutVite();

        $this->get('/admin/login')->assertOk();
    }

    public function test_admins_can_authenticate_via_admin_login(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_customers_cannot_use_admin_login(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_admin_login_from_protected_area_uses_intended_url(): void
    {
        $admin = User::factory()->admin()->create();

        $this->get('/admin/products');

        $response = $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/admin/products');
    }

    public function test_guest_admin_routes_redirect_to_admin_login(): void
    {
        $this->get('/admin/products')
            ->assertRedirect(route('admin.login', absolute: false));
    }

    public function test_admins_can_logout_to_admin_login(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/admin/logout');

        $this->assertGuest();
        $response->assertRedirect(route('admin.login', absolute: false));
    }
}

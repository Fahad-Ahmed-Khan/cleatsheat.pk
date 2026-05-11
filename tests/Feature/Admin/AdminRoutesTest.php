<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect();
    }

    public function test_admin_can_view_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertStatus(200);
    }

    public function test_admin_can_open_product_create_form(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.products.create'))
            ->assertStatus(200);
    }
}

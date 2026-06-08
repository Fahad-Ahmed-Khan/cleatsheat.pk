<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_admin_user(): void
    {
        $this->artisan('admin:create-user', [
            'email' => 'ops@tryino.test',
            '--name' => 'Ops',
            '--password' => 'secret-pass',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'ops@tryino.test')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->isAdmin());
    }

    public function test_it_promotes_existing_customer_to_admin(): void
    {
        $user = User::factory()->create(['email' => 'staff@tryino.test']);

        $this->artisan('admin:create-user', [
            'email' => 'staff@tryino.test',
            '--password' => 'new-secret',
        ])->assertSuccessful();

        $user->refresh();

        $this->assertSame(UserRole::Admin, $user->role);
    }
}

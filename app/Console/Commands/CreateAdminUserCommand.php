<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAdminUserCommand extends Command
{
    protected $signature = 'admin:create-user
                            {email : Staff login email}
                            {--name=Admin : Display name}
                            {--password= : Plain password (prompted when omitted)}';

    protected $description = 'Create or promote a staff admin account (for production bootstrap)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address.');

            return self::FAILURE;
        }

        $name = trim((string) $this->option('name'));
        $password = (string) ($this->option('password') ?: $this->secret('Password'));

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $user->forceFill([
                'name' => $name !== '' ? $name : $user->name,
                'password' => Hash::make($password),
                'role' => UserRole::Admin,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            $this->info("Updated existing user {$email} to admin.");

            return self::SUCCESS;
        }

        User::query()->create([
            'name' => $name !== '' ? $name : 'Admin',
            'email' => $email,
            'password' => Hash::make($password),
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
            'locale' => 'en',
        ]);

        $this->info("Created admin user {$email}.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Domain\Api;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ApiAuthenticationService
{
    public function attemptLogin(string $email, string $password, string $ip): ?User
    {
        $key = $this->throttleKey($email, $ip);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => [__('auth.throttle', ['seconds' => $seconds, 'minutes' => (int) ceil($seconds / 60)])],
            ]);
        }

        $user = User::query()->where('email', mb_strtolower($email))->first();
        if ($user === null || ! Hash::check($password, (string) $user->password)) {
            RateLimiter::hit($key);

            return null;
        }

        RateLimiter::clear($key);

        return $user;
    }

    public function createBearerToken(User $user, string $deviceName = 'mobile'): string
    {
        return $user->createToken($deviceName)->plainTextToken;
    }

    public function revokeCurrentToken(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function revokeAllTokens(User $user): void
    {
        $user->tokens()->delete();
    }

    private function throttleKey(string $email, string $ip): string
    {
        return Str::transliterate(Str::lower($email).'|'.$ip);
    }
}

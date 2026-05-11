<?php

namespace App\Support\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Resolves a User from an Authorization: Bearer personal access token
 * without requiring the auth:sanctum middleware (for guest-or-user routes).
 */
final class SanctumBearerUser
{
    public static function resolve(Request $request): ?User
    {
        $bearer = $request->bearerToken();
        if ($bearer === null || $bearer === '') {
            return null;
        }

        $token = PersonalAccessToken::findToken($bearer);
        $model = $token?->tokenable;

        return $model instanceof User ? $model : null;
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounts\UserRegistrationService;
use App\Domain\Api\ApiAuthenticationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ApiLoginRequest;
use App\Http\Requests\Api\V1\ApiRegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Support\Api\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly ApiAuthenticationService $auth,
        private readonly UserRegistrationService $registration,
    ) {}

    public function register(ApiRegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $this->registration->registerCustomer(
            (string) $data['name'],
            (string) $data['email'],
            (string) $data['password'],
        );

        $device = (string) ($data['device_name'] ?? 'mobile');
        $plainToken = $this->auth->createBearerToken($user, $device);

        return ApiResponder::ok([
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'user' => (new UserResource($user))->resolve(),
        ], 201);
    }

    public function login(ApiLoginRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $user = $this->auth->attemptLogin(
                (string) $data['email'],
                (string) $data['password'],
                (string) $request->ip(),
            );
        } catch (ValidationException $e) {
            return ApiResponder::error(
                $e->getMessage(),
                429,
                $e->errors(),
                code: 'auth_throttled',
            );
        }

        if ($user === null) {
            return ApiResponder::error('Invalid credentials.', 401, code: 'auth_invalid');
        }

        $device = (string) ($data['device_name'] ?? 'mobile');
        $plainToken = $this->auth->createBearerToken($user, $device);

        return ApiResponder::ok([
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'user' => (new UserResource($user))->resolve(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->revokeCurrentToken($request->user());

        return ApiResponder::ok(['revoked' => true]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $this->auth->revokeAllTokens($request->user());

        return ApiResponder::ok(['revoked_all' => true]);
    }

    public function user(Request $request): JsonResponse
    {
        return ApiResponder::ok((new UserResource($request->user()))->resolve());
    }
}

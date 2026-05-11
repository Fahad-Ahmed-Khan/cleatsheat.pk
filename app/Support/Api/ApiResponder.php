<?php

namespace App\Support\Api;

use Illuminate\Http\JsonResponse;

final class ApiResponder
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function ok(mixed $data = null, int $status = 200, array $meta = []): JsonResponse
    {
        $payload = ['success' => true];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        $baseMeta = [
            'api' => [
                'version' => config('api.version', '1.0.0'),
                'path' => 'api/'.config('api.path_version', 'v1'),
            ],
        ];
        $mergedMeta = array_replace_recursive($baseMeta, $meta);
        $payload['meta'] = $mergedMeta;

        return response()->json($payload, $status);
    }

    /**
     * @param  array<string, array<int, string>|string>  $errors
     */
    public static function error(
        string $message,
        int $status = 400,
        array $errors = [],
        ?string $code = null,
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($code !== null) {
            $payload['code'] = $code;
        }

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        $payload['meta'] = [
            'api' => [
                'version' => config('api.version', '1.0.0'),
                'path' => 'api/'.config('api.path_version', 'v1'),
            ],
        ];

        return response()->json($payload, $status);
    }
}

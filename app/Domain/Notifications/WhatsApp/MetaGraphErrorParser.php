<?php

namespace App\Domain\Notifications\WhatsApp;

use Illuminate\Http\Client\RequestException;

final class MetaGraphErrorParser
{
    public static function summarize(\Throwable $e): string
    {
        if (! $e instanceof RequestException) {
            return $e->getMessage();
        }

        $response = $e->response;
        if ($response === null) {
            return $e->getMessage();
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];
        $error = is_array($json['error'] ?? null) ? $json['error'] : [];

        $parts = array_filter([
            isset($error['error_user_msg']) ? (string) $error['error_user_msg'] : null,
            isset($error['error_data']['details']) ? (string) $error['error_data']['details'] : null,
            isset($error['message']) ? (string) $error['message'] : null,
            isset($error['error_subcode']) ? 'subcode '.(string) $error['error_subcode'] : null,
            isset($error['code']) ? 'code '.(string) $error['code'] : null,
        ]);

        if ($parts === []) {
            return $e->getMessage();
        }

        return implode(' | ', $parts);
    }
}

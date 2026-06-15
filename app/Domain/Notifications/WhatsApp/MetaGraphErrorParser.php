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

        $subcode = isset($error['error_subcode']) ? (int) $error['error_subcode'] : null;

        $parts = array_filter([
            self::hintForSubcode($subcode),
            isset($error['error_user_msg']) ? (string) $error['error_user_msg'] : null,
            isset($error['error_data']['details']) ? (string) $error['error_data']['details'] : null,
            isset($error['message']) ? (string) $error['message'] : null,
            $subcode !== null ? 'subcode '.$subcode : null,
            isset($error['code']) ? 'code '.(string) $error['code'] : null,
        ]);

        if ($parts === []) {
            return $e->getMessage();
        }

        return implode(' | ', $parts);
    }

    private static function hintForSubcode(?int $subcode): ?string
    {
        return match ($subcode) {
            2388023 => 'Meta template name already exists (or is pending deletion). Set a new Meta template name in admin (e.g. order_placed_v2) and sync again.',
            2388024 => 'Meta template limit reached for this WhatsApp Business Account. Delete unused templates in Meta Business Manager.',
            default => null,
        };
    }
}

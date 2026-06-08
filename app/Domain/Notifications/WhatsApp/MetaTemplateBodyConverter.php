<?php

namespace App\Domain\Notifications\WhatsApp;

use App\Models\Order;

/**
 * Converts admin-editable template bodies ({name}, {order}, …) into Meta
 * message-template BODY text ({{1}}, {{2}}, …) with sample values.
 */
final class MetaTemplateBodyConverter
{
    /** @var array<string, string> */
    private const EXAMPLES = [
        'name' => 'Test Customer',
        'order' => 'ORD-1001',
        'total' => '4500',
        'status' => 'Processing',
        'payment' => 'COD',
        'phone' => '+923001234567',
        'city' => 'Karachi',
        'parcels' => '3',
        'cod_total' => '12000',
        'tracking_list' => 'TRK-001, TRK-002',
        'courier' => 'PostEx',
    ];

    /**
     * @return array{
     *     body: string,
     *     parameter_order: list<string>,
     *     examples: list<string>
     * }
     */
    public static function convert(string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            throw new \InvalidArgumentException('Template body cannot be empty.');
        }

        preg_match_all('/\{([a-z_]+)\}/', $body, $matches, PREG_OFFSET_CAPTURE);

        /** @var list<string> $parameterOrder */
        $parameterOrder = [];
        $seen = [];

        foreach ($matches[1] as $match) {
            $key = (string) $match[0];
            if (isset($seen[$key])) {
                continue;
            }
            if (! array_key_exists($key, self::EXAMPLES)) {
                throw new \InvalidArgumentException("Unsupported placeholder {{$key}} for Meta sync.");
            }
            $seen[$key] = true;
            $parameterOrder[] = $key;
        }

        $metaBody = $body;
        $index = 1;
        foreach ($parameterOrder as $key) {
            $metaBody = str_replace('{'.$key.'}', '{{'.$index.'}}', $metaBody);
            $index++;
        }

        $metaBody = self::normalizeMetaBody($metaBody, count($parameterOrder));

        if (mb_strlen($metaBody) > 1024) {
            throw new \InvalidArgumentException('Meta template body exceeds 1024 characters.');
        }

        $examples = array_map(
            static fn (string $key): string => self::EXAMPLES[$key],
            $parameterOrder,
        );

        return [
            'body' => $metaBody,
            'parameter_order' => $parameterOrder,
            'examples' => $examples,
        ];
    }

    /**
     * Meta rejects templates when variables start/end the body or outnumber static words.
     * Padding is added only to the Meta submission — the admin-editable body is unchanged.
     */
    private static function normalizeMetaBody(string $metaBody, int $variableCount): string
    {
        if ($variableCount === 0) {
            return trim($metaBody);
        }

        $body = trim($metaBody);

        if (preg_match('/^\{\{\d+\}\}/', $body)) {
            $body = 'Hello, '.$body;
        }

        if (preg_match('/\{\{\d+\}\}\s*$/', $body)) {
            $body = rtrim($body).' — Tryino.';
        }

        $fillers = [
            ' This is an automated notification from Tryino.',
            ' Please review and action this promptly.',
            ' Contact us on WhatsApp if you need assistance.',
        ];

        foreach ($fillers as $filler) {
            if (self::staticWordCount($body) / $variableCount >= 3) {
                break;
            }

            $body .= $filler;

            if (mb_strlen($body) > 1020) {
                break;
            }
        }

        return trim($body);
    }

    private static function staticWordCount(string $metaBody): int
    {
        $static = trim(preg_replace('/\{\{\d+\}\}/', ' ', $metaBody) ?? '');
        if ($static === '') {
            return 0;
        }

        $parts = preg_split('/\s+/u', $static, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($parts) ? count($parts) : 0;
    }

    /**
     * @param  list<string>  $parameterOrder
     */
    public static function resolveParameterValues(array $parameterOrder, ?Order $order, string $shortStatus = 'Update'): array
    {
        $values = [];

        foreach ($parameterOrder as $key) {
            $values[] = self::valueForKey($key, $order, $shortStatus);
        }

        return $values;
    }

    private static function valueForKey(string $key, ?Order $order, string $shortStatus): string
    {
        if ($order === null) {
            return self::EXAMPLES[$key] ?? '';
        }

        $snap = is_array($order->shipping_address_snapshot) ? $order->shipping_address_snapshot : [];

        return match ($key) {
            'name' => (string) ($snap['full_name'] ?? 'Customer'),
            'order' => (string) $order->order_number,
            'total' => (string) $order->grand_total,
            'status' => $shortStatus !== '' ? $shortStatus : (string) $order->status->value,
            'payment' => (string) ($order->payment_gateway ?? ''),
            'phone' => (string) ($snap['phone'] ?? ''),
            'city' => (string) ($snap['city'] ?? ''),
            default => self::EXAMPLES[$key] ?? '',
        };
    }
}

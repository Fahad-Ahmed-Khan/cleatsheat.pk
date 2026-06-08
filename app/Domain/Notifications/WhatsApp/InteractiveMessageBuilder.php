<?php

namespace App\Domain\Notifications\WhatsApp;

use App\Models\Order;

/**
 * Builds WhatsApp Cloud API payloads for non-text message types
 * (interactive buttons, approved template messages with parameter binding).
 *
 * Kept separate from WhatsAppNotifier so each shape can be tested in isolation
 * and so the notifier stays focused on routing + logging.
 */
final class InteractiveMessageBuilder
{
    /**
     * Build an `interactive.button` payload with up to 3 reply buttons.
     *
     * @param  list<array{id:string,title:string}>  $buttons
     * @return array<string, mixed>
     */
    public static function buttonPayload(string $toE164, string $body, array $buttons): array
    {
        $apiButtons = [];
        foreach (array_slice($buttons, 0, 3) as $btn) {
            $id = (string) ($btn['id'] ?? '');
            $title = (string) ($btn['title'] ?? '');
            if ($id === '' || $title === '') {
                continue;
            }
            $apiButtons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => mb_substr($id, 0, 256),
                    'title' => mb_substr($title, 0, 20),
                ],
            ];
        }

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => ltrim($toE164, '+'),
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => mb_substr($body, 0, 1024)],
                'action' => ['buttons' => $apiButtons],
            ],
        ];
    }

    /**
     * Build a Cloud API approved-template payload with 4 body parameters
     * (name, order#, total, status). This is the only way to message a
     * customer outside the 24-hour session window.
     *
     * @return array<string, mixed>
     */
    /**
     * @param  list<string>|null  $parameterOrder  Placeholder keys in Meta {{1}}..{{n}} order
     */
    public static function cloudTemplatePayload(
        string $toE164,
        string $templateName,
        string $language,
        Order $order,
        string $shortStatus,
        ?array $parameterOrder = null,
    ): array {
        $keys = $parameterOrder !== null && $parameterOrder !== []
            ? $parameterOrder
            : ['name', 'order', 'total', 'status'];

        $values = MetaTemplateBodyConverter::resolveParameterValues($keys, $order, $shortStatus);

        $parameters = array_map(
            static fn (string $text): array => ['type' => 'text', 'text' => $text],
            $values,
        );

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => ltrim($toE164, '+'),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language !== '' ? $language : 'en_US'],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => $parameters,
                    ],
                ],
            ],
        ];
    }
}

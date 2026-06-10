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
     * Build an `interactive.button` payload with up to 3 reply buttons and
     * optional text header / footer.
     *
     * @param  list<array{id:string,title:string}>  $buttons
     * @return array<string, mixed>
     */
    public static function buttonPayload(string $toE164, string $body, array $buttons, ?string $headerText = null, ?string $footerText = null): array
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

        $interactive = [
            'type' => 'button',
            'body' => ['text' => mb_substr($body, 0, 1024)],
            'action' => ['buttons' => $apiButtons],
        ];

        if (is_string($headerText) && trim($headerText) !== '') {
            $interactive['header'] = ['type' => 'text', 'text' => mb_substr(trim($headerText), 0, 60)];
        }

        if (is_string($footerText) && trim($footerText) !== '') {
            $interactive['footer'] = ['text' => mb_substr(trim($footerText), 0, 60)];
        }

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => ltrim($toE164, '+'),
            'type' => 'interactive',
            'interactive' => $interactive,
        ];
    }

    /**
     * Build a Cloud API approved-template payload with body parameters and
     * dynamic URL button parameters. This is the only way to message a
     * customer outside the 24-hour session window.
     *
     * @param  list<string>|null  $parameterOrder  Placeholder keys in Meta {{1}}..{{n}} order
     * @param  list<array{text:string,url:string}>  $urlButtons  URL buttons as stored on the template; a `{order_number}` token marks a dynamic suffix
     * @return array<string, mixed>
     */
    public static function cloudTemplatePayload(
        string $toE164,
        string $templateName,
        string $language,
        Order $order,
        string $shortStatus,
        ?array $parameterOrder = null,
        array $urlButtons = [],
    ): array {
        $keys = $parameterOrder !== null && $parameterOrder !== []
            ? $parameterOrder
            : ['name', 'order', 'total', 'status'];

        $values = MetaTemplateBodyConverter::resolveParameterValues($keys, $order, $shortStatus);

        $parameters = array_map(
            static fn (string $text): array => ['type' => 'text', 'text' => $text],
            $values,
        );

        $components = [
            [
                'type' => 'body',
                'parameters' => $parameters,
            ],
        ];

        foreach (array_values(array_slice($urlButtons, 0, 2)) as $index => $button) {
            $url = (string) ($button['url'] ?? '');
            if (! str_contains($url, '{order_number}')) {
                continue; // Static URL buttons need no send-time parameter.
            }

            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => $index,
                'parameters' => [
                    ['type' => 'text', 'text' => (string) $order->order_number],
                ],
            ];
        }

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => ltrim($toE164, '+'),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language !== '' ? $language : 'en_US'],
                'components' => $components,
            ],
        ];
    }
}

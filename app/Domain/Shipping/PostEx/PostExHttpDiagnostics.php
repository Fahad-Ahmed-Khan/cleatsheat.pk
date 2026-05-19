<?php

namespace App\Domain\Shipping\PostEx;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

final class PostExHttpDiagnostics
{
    public static function summarizeFailedResponse(Response $res): string
    {
        $status = $res->status();
        $json = $res->json();
        if (is_array($json)) {
            foreach (['statusMessage', 'message', 'error'] as $key) {
                if (! empty($json[$key]) && (is_string($json[$key]) || is_numeric($json[$key]))) {
                    return 'HTTP '.$status.': '.(string) $json[$key];
                }
            }
        }

        $body = trim(strip_tags($res->body()));
        if ($body !== '') {
            return 'HTTP '.$status.': '.Str::limit($body, 200);
        }

        return 'HTTP '.$status.'.';
    }
}

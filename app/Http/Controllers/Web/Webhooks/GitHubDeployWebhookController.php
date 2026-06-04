<?php

namespace App\Http\Controllers\Web\Webhooks;

use App\Http\Controllers\Controller;
use App\Support\Deploy\HostingerDeployRunner;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives GitHub push webhooks after CI updates the `production` branch.
 *
 * Verifies X-Hub-Signature-256, ignores non-production refs, then starts
 * pull-deploy.sh in the background so the webhook returns within GitHub's timeout.
 */
class GitHubDeployWebhookController extends Controller
{
    public function __construct(
        private readonly HostingerDeployRunner $deployRunner,
    ) {}

    public function handle(Request $request): Response
    {
        if (! config('deploy.enabled')) {
            return response('Deploy webhook is disabled.', 503);
        }

        $secret = (string) config('deploy.webhook_secret', '');
        if ($secret === '') {
            Log::warning('deploy.github.webhook.unconfigured');

            return response('Deploy webhook secret is not configured.', 503);
        }

        $event = (string) $request->header('X-GitHub-Event', '');

        if ($event === 'ping') {
            return response('pong', 200);
        }

        if ($event !== 'push') {
            return response('Ignored', 200);
        }

        if (! $this->verifySignature($request, $secret)) {
            Log::warning('deploy.github.webhook.bad_signature', [
                'ip' => $request->ip(),
            ]);

            return response('Invalid signature', 401);
        }

        $payload = $request->json()->all();
        $ref = (string) ($payload['ref'] ?? '');
        $expectedRef = 'refs/heads/'.config('deploy.branch', 'production');

        if ($ref !== $expectedRef) {
            return response('Ignored', 200);
        }

        try {
            $this->deployRunner->runInBackground();
        } catch (\Throwable $e) {
            Log::error('deploy.github.webhook.start_failed', [
                'message' => $e->getMessage(),
            ]);

            return response('Deploy could not be started.', 500);
        }

        Log::info('deploy.github.webhook.accepted', [
            'after' => $payload['after'] ?? null,
        ]);

        return response('Deploy started.', 202);
    }

    private function verifySignature(Request $request, string $secret): bool
    {
        $signature = (string) $request->header('X-Hub-Signature-256', '');
        if ($signature === '' || ! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}

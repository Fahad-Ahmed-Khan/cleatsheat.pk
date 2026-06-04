<?php

namespace Tests\Feature\Deploy;

use App\Support\Deploy\HostingerDeployRunner;
use Tests\TestCase;

class GitHubDeployWebhookTest extends TestCase
{
    private function signedPost(string $body, string $secret): \Illuminate\Testing\TestResponse
    {
        $signature = 'sha256='.hash_hmac('sha256', $body, $secret);

        return $this->call(
            'POST',
            route('webhooks.github.deploy'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_GITHUB_EVENT' => 'push',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
            ],
            $body,
        );
    }

    public function test_ping_returns_pong(): void
    {
        config(['deploy.enabled' => true, 'deploy.webhook_secret' => 'sec']);

        $response = $this->postJson(route('webhooks.github.deploy'), [], [
            'X-GitHub-Event' => 'ping',
        ]);

        $response->assertOk();
        $response->assertSeeText('pong');
    }

    public function test_returns_503_when_disabled(): void
    {
        config(['deploy.enabled' => false, 'deploy.webhook_secret' => 'sec']);

        $response = $this->signedPost('{}', 'sec');

        $response->assertStatus(503);
    }

    public function test_returns_503_when_secret_missing(): void
    {
        config(['deploy.enabled' => true, 'deploy.webhook_secret' => '']);

        $response = $this->signedPost('{}', 'sec');

        $response->assertStatus(503);
    }

    public function test_rejects_invalid_signature(): void
    {
        config(['deploy.enabled' => true, 'deploy.webhook_secret' => 'sec']);

        $response = $this->postJson(route('webhooks.github.deploy'), ['ref' => 'refs/heads/production'], [
            'X-GitHub-Event' => 'push',
            'X-Hub-Signature-256' => 'sha256=deadbeef',
        ]);

        $response->assertStatus(401);
    }

    public function test_ignores_push_to_other_branches(): void
    {
        config(['deploy.enabled' => true, 'deploy.webhook_secret' => 'sec', 'deploy.branch' => 'production']);

        $this->mock(HostingerDeployRunner::class, function ($mock): void {
            $mock->shouldNotReceive('runInBackground');
        });

        $body = json_encode(['ref' => 'refs/heads/master'], JSON_THROW_ON_ERROR);
        $response = $this->signedPost($body, 'sec');

        $response->assertOk();
        $response->assertSeeText('Ignored');
    }

    public function test_valid_production_push_starts_deploy(): void
    {
        config(['deploy.enabled' => true, 'deploy.webhook_secret' => 'sec', 'deploy.branch' => 'production']);

        $this->mock(HostingerDeployRunner::class, function ($mock): void {
            $mock->shouldReceive('runInBackground')->once();
        });

        $body = json_encode([
            'ref' => 'refs/heads/production',
            'after' => 'abc123',
        ], JSON_THROW_ON_ERROR);

        $response = $this->signedPost($body, 'sec');

        $response->assertStatus(202);
        $response->assertSeeText('Deploy started');
    }
}

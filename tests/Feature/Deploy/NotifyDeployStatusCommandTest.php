<?php

namespace Tests\Feature\Deploy;

use App\Mail\DeployStatusMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotifyDeployStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_skips_when_notifications_disabled(): void
    {
        Mail::fake();

        config(['deploy.notify_enabled' => false]);

        $this->artisan('deploy:notify', ['status' => 'success'])
            ->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_command_sends_mail_when_enabled(): void
    {
        Mail::fake();

        config([
            'deploy.notify_enabled' => true,
            'deploy.notify_emails' => ['ops@example.com'],
            'app.url' => 'https://tryinotech.cloud',
        ]);

        $this->artisan('deploy:notify', [
            'status' => 'failed',
            '--detail' => 'Branch production, stage all. Exit code 1.',
            '--source' => 'production',
        ])->assertSuccessful();

        Mail::assertSent(DeployStatusMail::class, function (DeployStatusMail $mail): bool {
            return $mail->status === 'failed'
                && $mail->source === 'production'
                && str_contains($mail->detail, 'Exit code 1')
                && $mail->hasTo('ops@example.com');
        });
    }
}

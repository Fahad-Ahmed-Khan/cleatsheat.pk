<?php

namespace App\Console\Commands;

use App\Mail\DeployStatusMail;
use App\Support\Sentry\ExceptionLogging;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class NotifyDeployStatusCommand extends Command
{
    protected $signature = 'deploy:notify
                            {status : success or failed}
                            {--detail= : Extra context (branch, stage, error hint)}
                            {--source=production : Label shown in the email (e.g. production, ci)}';

    protected $description = 'Email configured addresses when a deploy succeeds or fails';

    public function handle(): int
    {
        if (! config('deploy.notify_enabled')) {
            return self::SUCCESS;
        }

        $status = strtolower((string) $this->argument('status'));
        if (! in_array($status, ['success', 'failed'], true)) {
            $this->error('Status must be success or failed.');

            return self::FAILURE;
        }

        $recipients = config('deploy.notify_emails', []);
        if ($recipients === []) {
            $this->warn('DEPLOY_NOTIFY_EMAIL is not set; skipping notification.');

            return self::SUCCESS;
        }

        $detail = trim((string) $this->option('detail'));
        if ($detail === '') {
            $detail = $status === 'success'
                ? 'Deploy finished without errors.'
                : 'Deploy exited with a non-zero status. Check storage/logs/deploy.log on the server.';
        }

        $source = (string) $this->option('source');
        $appUrl = rtrim((string) config('app.url'), '/') ?: 'unknown';

        try {
            Mail::to($recipients)->send(new DeployStatusMail(
                status: $status,
                source: $source,
                detail: $detail,
                appUrl: $appUrl,
            ));
        } catch (\Throwable $e) {
            ExceptionLogging::report($e, 'deploy.notify.mail_failed', [
                'status' => $status,
                'source' => $source,
            ]);

            $this->error('Failed to send deploy notification: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Deploy notification sent to: '.implode(', ', $recipients));

        return self::SUCCESS;
    }
}

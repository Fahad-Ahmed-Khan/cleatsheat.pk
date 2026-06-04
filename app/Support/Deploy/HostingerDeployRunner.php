<?php

namespace App\Support\Deploy;

use Illuminate\Support\Facades\Log;

/**
 * Starts the Hostinger pull-and-deploy shell script without blocking the HTTP response.
 *
 * Uses nohup + exec() because Hostinger PHP-FPM often disables proc_open, which breaks
 * Symfony Process::start().
 */
class HostingerDeployRunner
{
    public function runInBackground(): void
    {
        $script = base_path('scripts/hostinger/pull-deploy.sh');

        if (! is_file($script)) {
            throw new \RuntimeException("Deploy script not found: {$script}");
        }

        $logFile = storage_path('logs/deploy.log');
        $branch = (string) config('deploy.branch', 'production');
        $path = getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin:/opt/alt/php83/usr/bin';

        $command = sprintf(
            'cd %s && mkdir -p %s && nohup env DEPLOY_BRANCH=%s PATH=%s bash %s >> %s 2>&1 &',
            escapeshellarg(base_path()),
            escapeshellarg(dirname($logFile)),
            escapeshellarg($branch),
            escapeshellarg($path),
            escapeshellarg($script),
            escapeshellarg($logFile),
        );

        if (! function_exists('exec')) {
            throw new \RuntimeException('PHP exec() is disabled; cannot start deploy script.');
        }

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            Log::error('deploy.github.webhook.exec_failed', [
                'exit_code' => $exitCode,
                'output' => $output,
            ]);

            throw new \RuntimeException('Failed to start deploy script (exec exit '.$exitCode.').');
        }

        Log::info('deploy.github.webhook.spawned', [
            'branch' => $branch,
            'log' => $logFile,
        ]);
    }
}

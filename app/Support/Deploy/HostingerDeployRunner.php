<?php

namespace App\Support\Deploy;

use Illuminate\Support\Facades\Log;

/**
 * Starts the Hostinger pull-and-deploy shell script without blocking the HTTP response.
 *
 * Also writes a pending marker + deploy.log line synchronously so deploy still
 * runs via `deploy:run-pending` (scheduler) when shared hosting kills nohup children.
 */
class HostingerDeployRunner
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function runInBackground(array $meta = []): void
    {
        $script = base_path('scripts/hostinger/pull-deploy.sh');

        if (! is_file($script)) {
            throw new \RuntimeException("Deploy script not found: {$script}");
        }

        $logFile = storage_path('logs/deploy.log');
        $branch = (string) config('deploy.branch', 'production');
        $path = getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin:/opt/alt/php83/usr/bin';

        DeployPendingMarker::set($meta);
        $this->appendDeployLog(
            $logFile,
            'webhook queued deploy (branch='.$branch.', after='.($meta['after'] ?? 'unknown').'); pending marker set'
        );

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
            Log::warning('deploy.github.webhook.exec_disabled', [
                'log' => $logFile,
                'hint' => 'Deploy will run on next schedule via deploy:run-pending',
            ]);

            return;
        }

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            Log::error('deploy.github.webhook.exec_failed', [
                'exit_code' => $exitCode,
                'output' => $output,
                'hint' => 'Deploy will run on next schedule via deploy:run-pending',
            ]);

            return;
        }

        Log::info('deploy.github.webhook.spawned', [
            'branch' => $branch,
            'log' => $logFile,
        ]);
    }

    private function appendDeployLog(string $logFile, string $message): void
    {
        $dir = dirname($logFile);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $line = '['.gmdate('Y-m-d\TH:i:s\Z').'] '.$message.PHP_EOL;
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}

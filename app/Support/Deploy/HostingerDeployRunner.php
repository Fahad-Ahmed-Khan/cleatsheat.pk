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
        $home = getenv('HOME') ?: null;
        if (! is_string($home) || $home === '') {
            $user = get_current_user();
            $candidate = '/home/'.$user;
            $home = is_dir($candidate) ? $candidate : sys_get_temp_dir();
        }
        $composerHome = getenv('COMPOSER_HOME') ?: $home.'/.composer';

        DeployPendingMarker::set($meta);
        $this->appendDeployLog(
            $logFile,
            'webhook queued deploy (branch='.$branch.', after='.($meta['after'] ?? 'unknown').'); pending marker set'
        );

        $command = sprintf(
            'cd %s && mkdir -p %s %s && nohup env HOME=%s COMPOSER_HOME=%s DEPLOY_BRANCH=%s PATH=%s bash %s >> %s 2>&1 &',
            escapeshellarg(base_path()),
            escapeshellarg(dirname($logFile)),
            escapeshellarg($composerHome),
            escapeshellarg($home),
            escapeshellarg($composerHome),
            escapeshellarg($branch),
            escapeshellarg($path),
            escapeshellarg($script),
            escapeshellarg($logFile),
        );

        if (DeployShellRunner::isAvailable()) {
            try {
                DeployShellRunner::startInBackground($command, [], base_path());
                Log::info('deploy.github.webhook.spawned', [
                    'branch' => $branch,
                    'log' => $logFile,
                    'via' => 'proc_open',
                ]);

                return;
            } catch (\Throwable $e) {
                Log::warning('deploy.github.webhook.proc_open_failed', [
                    'message' => $e->getMessage(),
                    'hint' => 'Deploy will run on next schedule via deploy:run-pending',
                ]);
            }
        } elseif (function_exists('exec') && ! in_array('exec', array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions')))), true)) {
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);

            if ($exitCode === 0) {
                Log::info('deploy.github.webhook.spawned', [
                    'branch' => $branch,
                    'log' => $logFile,
                    'via' => 'exec',
                ]);

                return;
            }

            Log::error('deploy.github.webhook.exec_failed', [
                'exit_code' => $exitCode,
                'output' => $output,
                'hint' => 'Deploy will run on next schedule via deploy:run-pending',
            ]);

            return;
        }

        Log::warning('deploy.github.webhook.shell_disabled', [
            'log' => $logFile,
            'hint' => 'Deploy will run on next schedule via deploy:run-pending',
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

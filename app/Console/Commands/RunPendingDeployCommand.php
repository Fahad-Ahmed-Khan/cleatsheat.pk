<?php

namespace App\Console\Commands;

use App\Support\Deploy\DeployPendingMarker;
use App\Support\Deploy\DeployShellRunner;
use App\Support\Sentry\ExceptionLogging;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunPendingDeployCommand extends Command
{
    protected $signature = 'deploy:run-pending';

    protected $description = 'Run pull-deploy.sh when the GitHub webhook left a pending marker';

    public function handle(): int
    {
        if (! DeployPendingMarker::exists()) {
            return self::SUCCESS;
        }

        $meta = DeployPendingMarker::read();
        $script = base_path('scripts/hostinger/pull-deploy.sh');

        if (! is_file($script)) {
            $this->error('Deploy script not found: '.$script);
            Log::error('deploy.run_pending.missing_script', ['path' => $script]);

            return self::FAILURE;
        }

        $logFile = storage_path('logs/deploy.log');
        $branch = (string) ($meta['branch'] ?? config('deploy.branch', 'production'));

        $this->appendDeployLog($logFile, 'deploy:run-pending starting pull-deploy (branch='.$branch.')');

        $home = getenv('HOME') ?: null;
        if (! is_string($home) || $home === '') {
            $user = get_current_user();
            $candidate = '/home/'.$user;
            $home = is_dir($candidate) ? $candidate : sys_get_temp_dir();
        }

        $composerHome = getenv('COMPOSER_HOME') ?: $home.'/.composer';
        if (! is_dir($composerHome)) {
            @mkdir($composerHome, 0755, true);
        }

        if (! DeployShellRunner::isAvailable()) {
            $this->error('Shell execution is disabled on this host (proc_open unavailable).');
            $this->appendDeployLog($logFile, 'deploy:run-pending skipped: proc_open disabled; use cron bash fallback');
            Log::warning('deploy.run_pending.shell_disabled', [
                'branch' => $branch,
                'hint' => 'Add a cron line: test -f storage/framework/deploy-pending.json && bash scripts/hostinger/pull-deploy.sh',
            ]);

            return self::FAILURE;
        }

        $path = getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin:/opt/alt/php83/usr/bin';

        try {
            $ok = DeployShellRunner::run(
                sprintf('bash %s', escapeshellarg($script)),
                $exitCode,
                [
                    'HOME' => $home,
                    'COMPOSER_HOME' => $composerHome,
                    'DEPLOY_BRANCH' => $branch,
                    'PATH' => $path,
                ],
                base_path(),
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            ExceptionLogging::report($e, 'deploy.run_pending.exception', ['branch' => $branch]);

            return self::FAILURE;
        }

        if ($ok) {
            DeployPendingMarker::clear();
            $this->info('Deploy finished successfully.');
            Log::info('deploy.run_pending.success', ['branch' => $branch]);

            return self::SUCCESS;
        }

        $this->error('Deploy failed with exit code '.$exitCode.'. Pending marker kept for retry.');
        Log::error('deploy.run_pending.failed', [
            'branch' => $branch,
            'exit_code' => $exitCode,
        ]);

        return self::FAILURE;
    }

    private function appendDeployLog(string $logFile, string $message): void
    {
        $dir = dirname($logFile);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $line = '['.now()->utc()->format('Y-m-d\TH:i:s\Z').'] '.$message.PHP_EOL;
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}

<?php

namespace App\Console\Commands;

use App\Support\Deploy\DeployPendingMarker;
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

        $command = sprintf(
            'cd %s && env DEPLOY_BRANCH=%s bash %s',
            escapeshellarg(base_path()),
            escapeshellarg($branch),
            escapeshellarg($script),
        );

        passthru($command, $exitCode);

        if ($exitCode === 0) {
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

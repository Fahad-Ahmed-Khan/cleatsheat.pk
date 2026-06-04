<?php

namespace App\Support\Deploy;

use Symfony\Component\Process\Process;

/**
 * Starts the Hostinger pull-and-deploy shell script without blocking the HTTP response.
 */
class HostingerDeployRunner
{
    public function runInBackground(): void
    {
        $script = base_path('scripts/hostinger/pull-deploy.sh');

        if (! is_file($script)) {
            throw new \RuntimeException("Deploy script not found: {$script}");
        }

        $process = new Process(
            ['bash', $script],
            base_path(),
            [
                'DEPLOY_BRANCH' => (string) config('deploy.branch', 'production'),
                'HOME' => getenv('HOME') ?: '',
                'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
            ],
        );

        $process->setTimeout(null);
        $process->disableOutput();
        $process->start();
    }
}

<?php

namespace App\Support\Deploy;

use Symfony\Component\Process\Process;

/**
 * Runs shell commands on shared hosting where passthru/exec are disabled but proc_open works.
 */
final class DeployShellRunner
{
    public static function isAvailable(): bool
    {
        if (! function_exists('proc_open') || ! class_exists(Process::class)) {
            return false;
        }

        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));

        return ! in_array('proc_open', $disabled, true);
    }

    /**
     * @param  array<string, string>  $env
     */
    public static function run(string $command, ?int &$exitCode = null, array $env = [], ?string $cwd = null): bool
    {
        if (! self::isAvailable()) {
            throw new \RuntimeException('Shell commands cannot run: proc_open is disabled on this host.');
        }

        $process = Process::fromShellCommandline($command, $cwd, $env, null, null);
        $process->setTimeout(null);
        $process->run();

        $exitCode = $process->getExitCode() ?? 1;

        return $exitCode === 0;
    }

    /**
     * @param  array<string, string>  $env
     */
    public static function startInBackground(string $command, array $env = [], ?string $cwd = null): void
    {
        if (! self::isAvailable()) {
            throw new \RuntimeException('Shell commands cannot run: proc_open is disabled on this host.');
        }

        $process = Process::fromShellCommandline($command, $cwd, $env, null, null);
        $process->setTimeout(null);
        $process->start();
    }
}

<?php

namespace App\Support\Psy;

use Psy\ExecutionLoop\ProcessForker;
use Psy\ExecutionLoop\SignalHandler;
use Psy\Shell;

/**
 * PsySH shell without execution-loop listeners that require shell_exec.
 *
 * Hostinger and similar shared hosts often disable shell_exec in CLI, which
 * breaks PsySH's ProcessForker / SignalHandler stty setup.
 */
class SharedHostingShell extends Shell
{
    /**
     * @return array<int, object>
     */
    protected function getDefaultLoopListeners(): array
    {
        $listeners = parent::getDefaultLoopListeners();

        if (\function_exists('shell_exec')) {
            return $listeners;
        }

        return array_values(array_filter(
            $listeners,
            static fn (object $listener): bool => ! $listener instanceof ProcessForker
                && ! $listener instanceof SignalHandler,
        ));
    }
}

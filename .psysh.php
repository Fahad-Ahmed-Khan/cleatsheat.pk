<?php

/**
 * PsySH project config (used by `php artisan tinker` and standalone psysh).
 *
 * Shared hosts such as Hostinger disable shell_exec in CLI; these settings avoid
 * update checks and other features that depend on it.
 */
return [
    'updateCheck' => 'never',
    'updateManualCheck' => 'never',
    'usePcntl' => false,
];

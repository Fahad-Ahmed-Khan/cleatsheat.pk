<?php

namespace App\Console\Commands;

use App\Support\Psy\SharedHostingShell;
use Illuminate\Support\Env;
use Laravel\Tinker\ClassAliasAutoloader;
use Laravel\Tinker\Console\TinkerCommand as BaseTinkerCommand;
use Psy\Configuration;
use Psy\ManualUpdater\Checker as ManualChecker;
use Psy\VersionUpdater\Checker;
use Throwable;

class TinkerCommand extends BaseTinkerCommand
{
    public function handle()
    {
        $this->getApplication()->setCatchExceptions(false);

        $config = Configuration::fromInput($this->input);
        $config->setUpdateCheck(Checker::NEVER);
        $config->setUpdateManualCheck(ManualChecker::NEVER);

        $appConfig = $this->getLaravel()->make('config');
        $config->setTrustProject($appConfig->get('tinker.trust_project'));

        $config->getPresenter()->addCasters(
            $this->getCasters()
        );

        if ($this->option('execute')) {
            $config->setRawOutput(true);
        }

        $shell = new SharedHostingShell($config);
        $shell->addCommands($this->getCommands());
        $shell->setIncludes($this->argument('include'));

        $path = Env::get('COMPOSER_VENDOR_DIR', $this->getLaravel()->basePath().DIRECTORY_SEPARATOR.'vendor');
        $path .= '/composer/autoload_classmap.php';

        $loader = ClassAliasAutoloader::register(
            $shell, $path, $appConfig->get('tinker.alias', []), $appConfig->get('tinker.dont_alias', [])
        );

        if ($code = $this->option('execute')) {
            try {
                $shell->setOutput($this->output);
                $shell->execute($code, true);
            } catch (Throwable $e) {
                $shell->writeException($e);

                return 1;
            } finally {
                $loader->unregister();
            }

            return 0;
        }

        try {
            return $shell->run();
        } finally {
            $loader->unregister();
        }
    }
}

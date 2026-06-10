<?php

namespace Tests\Unit\Deploy;

use App\Support\Deploy\DeployShellRunner;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class DeployShellRunnerTest extends TestCase
{
    public function test_is_available_reflects_proc_open(): void
    {
        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        $expected = function_exists('proc_open') && ! in_array('proc_open', $disabled, true);

        $this->assertSame($expected, DeployShellRunner::isAvailable());
    }

    #[Group('shell')]
    public function test_run_executes_command_when_available(): void
    {
        if (! DeployShellRunner::isAvailable()) {
            $this->markTestSkipped('proc_open is not available in this PHP environment.');
        }

        $exitCode = -1;
        $ok = DeployShellRunner::run('exit 0', $exitCode);

        $this->assertTrue($ok);
        $this->assertSame(0, $exitCode);
    }
}

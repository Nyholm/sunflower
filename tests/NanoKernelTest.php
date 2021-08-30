<?php

declare(strict_types=1);

namespace Nyholm\NanoKernel\Tests;

use Example1\App\Kernel;
use Nyholm\NanoKernel\NanoKernel;
use PHPUnit\Framework\TestCase;

class NanoKernelTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        include_once __DIR__.'/Resources/app/src/Kernel.php';
    }

    public function testCanBeInitialized()
    {
        $kernel = new NanoKernel('dev', true);
        $this->assertInstanceOf(NanoKernel::class, $kernel);
        $kernel = new NanoKernel('dev', false);
        $this->assertInstanceOf(NanoKernel::class, $kernel);
        $kernel = new NanoKernel('prod', false);
        $this->assertInstanceOf(NanoKernel::class, $kernel);
    }

    public function testGetters()
    {
        $kernel = new class('dev', true) extends NanoKernel {
            public function getProjectDir(): string
            {
                return 'project-dir';
            }
        };

        $this->assertSame('project-dir/var/log', $kernel->getLogDir());
        $this->assertSame('project-dir/var/cache/dev', $kernel->getCacheDir());
        $this->assertSame('project-dir/var/cache/dev', $kernel->getBuildDir());
        $this->assertSame('project-dir/config', $kernel->getConfigDir());
    }

    public function testGetProject()
    {
        $kernel = new Kernel('dev', true);
        $this->assertSame(__DIR__.'/Resources/app', $kernel->getProjectDir());
    }
}

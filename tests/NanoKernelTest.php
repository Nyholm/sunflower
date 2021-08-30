<?php

declare(strict_types=1);

namespace Nyholm\NanoKernel\Tests;

use Example1\App\Kernel;
use Example1\App\Service\MyService;
use Nyholm\NanoKernel\NanoKernel;
use PHPUnit\Framework\TestCase;

class NanoKernelTest extends TestCase
{
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
        $this->assertSame(__DIR__.'/Resources/example1', $kernel->getProjectDir());
    }

    public function testBundleConfigured()
    {
        $kernel = new Kernel('dev', true);
        $container = $kernel->getContainer();
        $this->assertTrue($container->hasParameter('happyr_service_mock.services'));
        $this->assertTrue($container->has(MyService::class));
    }
}

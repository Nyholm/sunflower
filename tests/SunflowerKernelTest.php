<?php

declare(strict_types=1);

namespace Nyholm\Sumflower\Tests;

use Example1\App\Kernel;
use Example1\App\Service\MyService;
use Nyholm\SunflowerKernel;
use PHPUnit\Framework\TestCase;

class SunflowerKernelTest extends TestCase
{
    public function testCanBeInitialized()
    {
        $kernel = new SunflowerKernel('dev', true);
        $this->assertInstanceOf(SunflowerKernel::class, $kernel);
        $kernel = new SunflowerKernel('dev', false);
        $this->assertInstanceOf(SunflowerKernel::class, $kernel);
        $kernel = new SunflowerKernel('prod', false);
        $this->assertInstanceOf(SunflowerKernel::class, $kernel);
    }

    public function testGetters()
    {
        $kernel = new class('dev', true) extends SunflowerKernel {
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

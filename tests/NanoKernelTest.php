<?php

declare(strict_types=1);

namespace Nyholm\NanoKernel\Tests;

use Nyholm\NanoKernel\NanoKernel;
use PHPUnit\Framework\TestCase;

class NanoKernelTest extends TestCase
{
    public function testCanBeInitialized()
    {
        $kernel = new NanoKernel('dev', true);
        $this->assertInstanceOf(NanoKernel::class, $kernel);
    }
}

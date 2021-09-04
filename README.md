# Sunflower

Sunflower is a super small application kernel that is used to build a dependency
injection container. This kernel is useful for microservices and applications that
dont use HTTP. Say; reading from a queue or application invoked by AWS Lambda.

With this kernel you can use normal Symfony service definition with auto wiring
and all. It even supports Symfony bundles!

The main difference from using `symfony/http-kernel` and Symfony FrameworkBundle
is that Sunflower does not use `symfony/event-dispatcher`, `symfony/console`,
`symfony/security`, `symfony/cache` and `symfony/router`.

## Performance

Below is a table of requests per second using a "hello world" application with
different frameworks. The exact numbers are not relevant, they depend on the machine
the tests was running on. But one should consider how the numbers change between frameworks
since all test ran on the same machine.

| Framework           | Req/s |
|---------------------|-------|
| Sunflower           | 2.548
| Symfony 6.0         | 1.819
| Symfony 5.4         | 1.804
| Slim 4              | 1.380
| Mezzio 3            |   985
| Laravel 8           |   421

Using a "hello world" comparison has some drawbacks. It does show performance for
small applications with only a few hundreds lines of code, but it does not tell how
large applications preform. It also does not give you any indication how fast you
can write and maintain your application.

The table above is interesting if you are planning to build a small microservice
that are similar to "hello world". Using the Sunflower Kernel is also very interesting
if you are familiar with Symfony dependency injections, config and third party bundles.

## Install

```
composer require nyholm/sunflower
```

## Use

```php
// src/Kernel.php
namespace App;

use Nyholm\SunflowerKernel;

class Kernel extends SunflowerKernel
{
   /**
    * Optionally override the configureContainer()
    */
   protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import('../config/{packages}/*.yaml');
        $container->import('../config/{packages}/'.$this->environment.'/*.yaml');
        $container->import('../config/{packages}/'.$this->environment.'/*.php');

        if (\is_file(\dirname(__DIR__).'/config/services.yaml')) {
            $container->import('../config/services.yaml');
            $container->import('../config/{services}_'.$this->environment.'.yaml');
        } else {
            $container->import('../config/{services}.php');
        }
    }
}
```

```php

use App\Kernel;
use App\Service\MyService;

require_once dirname(__DIR__).'/vendor/autoload.php';

$kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
$kernel->getContainer()->get(MyService::class)->run();
```

## Use with HTTP

A short example using HTTP and a simple switch-router. This example is using
[runtime/psr-nyholm](https://github.com/php-runtime/psr-nyholm).

```php
// public/index.php

use Nyholm\Psr7;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    $kernel = new \App\Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
    $container = $kernel->getContainer();

    // This is an example router
    $urlPath = $context['REQUEST_URI'];
    switch ($urlPath) {
        case '/':
        case '':
            // This is an RequestHandlerInterface
            return $container->get(\App\Controller\Startpage::class);
        case '/foobar':
            return $container->get(\App\Controller\Foobar::class);
        default:
            return new Psr7\Response(404, [], 'The route does not exist');
    }
};
```

## Use with Bref

To create apps that works with [Bref](https://bref.sh/) you will need the
[runtime/bref](https://github.com/php-runtime/bref) package. Create microservices,
SQS readers or react to S3 events etc.

```php
// src/Kernel.php

namespace App;

use Nyholm\SunflowerKernel;

class Kernel extends SunflowerKernel
{
    public function isLambda(): bool
    {
        return false !== \getenv('LAMBDA_TASK_ROOT');
    }

    public function getCacheDir(): string
    {
        if ($this->isLambda()) {
            return '/tmp/cache/'.$this->environment;
        }

        return parent::getCacheDir();
    }

    public function getLogDir(): string
    {
        if ($this->isLambda()) {
            return '/tmp/log/';
        }

        return parent::getLogDir();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__);
    }
}
```

```php
// bin/container.php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    $kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);

    return $kernel->getContainer();
};
```

```yaml
# config/services.yaml

services:
    _defaults:
        autowire: true
        autoconfigure: true

    _instanceof:
        Bref\Event\Handler:
            public: true
```

```yaml
 # serverless.yml

  functions:
      app:
          handler: bin/container.php:App\Service\MyHandler
```

## History

The Sunflower project was open sourced in 2021. The very first version of the project
was created back in 2015. A few private applications was created around the concept of
using Symfony's Dependency Injection component but not use the FrameworkBundle or
HttpKernel.

The first *public* version of the project was [SuperSlim](https://github.com/Nyholm/SuperSlim).
That version was a opinionated framework to show what the FrameworkBundle actually
did for you behind the scenes. With some more private iterations and many more applications
created, we finally removed all unnecessary things and ended up with just the one
Kernel.

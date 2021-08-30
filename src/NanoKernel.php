<?php

declare(strict_types=1);

namespace Nyholm\NanoKernel;

use Bref\Event\Handler as BrefHandler;
use Symfony\Component\Config\Builder\ConfigBuilderGenerator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader as ContainerPhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\DependencyInjection\MergeExtensionConfigurationPass;

/**
 * A super tiny kernel that only warms up a Symfony container for you.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class NanoKernel
{
    protected bool $booted = false;
    protected bool $debug;
    protected string $environment;
    protected ?string $projectDir = null;
    private ContainerInterface $container;

    /**
     * @var BundleInterface[]
     */
    protected $bundles = [];

    public function __construct(string $env, bool $debug = false)
    {
        $this->debug = $debug;
        $this->environment = $env;
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import('../config/{packages}/*.yaml');
        $container->import('../config/{packages}/'.$this->environment.'/*.yaml');
        $container->import('../config/{packages}/'.$this->environment.'/*.php');

        if (\is_file($this->getConfigDir().'/services.yaml')) {
            $container->import('../config/services.yaml');
            $container->import('../config/{services}_'.$this->environment.'.yaml');
        } else {
            $container->import('../config/{services}.php');
        }
    }

    public function boot()
    {
        if ($this->booted) {
            return;
        }

        if (!file_exists($this->getCacheDir())) {
            mkdir($this->getCacheDir(), 0777, true);
        }

        $this->initializeBundles();

        $containerDumpFile = $this->getCacheDir().'/container.php';
        if ($this->debug || !\file_exists($containerDumpFile)) {
            $this->buildContainer($containerDumpFile);
        }

        require_once $containerDumpFile;
        $this->container = new \CachedContainer();

        foreach ($this->bundles as $bundle) {
            $bundle->setContainer($this->container);
            $bundle->boot();
        }

        $this->booted = true;
    }

    /**
     * Read config/bundles.php and initialize bundles.
     */
    protected function initializeBundles(): void
    {
        $this->bundles = [];
        $bundleConfigFile = $this->getConfigDir().'/bundles.php';
        if (!file_exists($bundleConfigFile)) {
            return;
        }

        $contents = require $bundleConfigFile;
        foreach ($contents as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                $bundle = new $class();
                $name = $bundle->getName();
                if (isset($this->bundles[$name])) {
                    throw new \LogicException(sprintf('Trying to register two bundles with the same name "%s".', $name));
                }
                $this->bundles[$name] = $bundle;
            }
        }
    }

    /**
     * Returns a loader for the container.
     *
     * @return DelegatingLoader The loader
     */
    protected function getContainerLoader(ContainerBuilder $container): DelegatingLoader
    {
        $locator = new FileLocator($this->getConfigDir());
        $resolver = new LoaderResolver([
            new YamlFileLoader($container, $locator, $this->environment),
            new PhpFileLoader($container, $locator, $this->environment, new ConfigBuilderGenerator($this->getBuildDir())),
            new GlobFileLoader($container, $locator, $this->environment),
            new DirectoryLoader($container, $locator, $this->environment),
            new ClosureLoader($container, $this->environment),
        ]);

        return new DelegatingLoader($resolver);
    }

    public function getContainer(): ContainerInterface
    {
        if (!$this->booted) {
            $this->boot();
        }

        return $this->container;
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/'.$this->environment;
    }

    public function getBuildDir(): string
    {
        return $this->getCacheDir();
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir().'/var/log';
    }

    /**
     * Gets the application root dir (path of the project's composer file).
     *
     * @return string The project root dir
     */
    public function getProjectDir(): string
    {
        if (null === $this->projectDir) {
            $r = new \ReflectionObject($this);

            if (!is_file($dir = $r->getFileName())) {
                throw new \LogicException(sprintf('Cannot auto-detect project dir for kernel of class "%s".', $r->name));
            }

            $dir = $rootDir = \dirname($dir);
            while (!is_file($dir.'/composer.json')) {
                if ($dir === \dirname($dir)) {
                    return $this->projectDir = $rootDir;
                }
                $dir = \dirname($dir);
            }
            $this->projectDir = $dir;
        }

        return $this->projectDir;
    }

    /**
     * The extension point similar to the Bundle::build() method.
     *
     * Use this method to register compiler passes and manipulate the container during the building process.
     */
    protected function build(ContainerBuilder $container)
    {
    }

    public function getConfigDir(): string
    {
        return $this->getProjectDir().'/config';
    }

    private function buildContainer(string $containerDumpFile): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $this->getProjectDir());
        $container->setParameter('kernel.cache_dir', $this->getCacheDir());
        $container->setParameter('kernel.log_dir', $this->getLogDir());
        $container->setParameter('kernel.environment', $this->environment);
        $container->setParameter('kernel.debug', $this->debug);

        // If bref/bref is installed
        if (interface_exists(BrefHandler::class)) {
            $container->registerForAutoconfiguration(BrefHandler::class)
                ->setPublic(true);
        }

        $configureContainer = new \ReflectionObject($this);
        $loader = $this->getContainerLoader($container);

        /** @var ContainerPhpFileLoader $kernelLoader */
        $kernelLoader = $loader->getResolver()->resolve($file = $configureContainer->getFileName());
        $kernelLoader->setCurrentDir(\dirname($file));
        $instanceof = &\Closure::bind(function &() {
            return $this->instanceof;
        }, $kernelLoader, $kernelLoader)();

        $this->configureContainer(new ContainerConfigurator($container, $kernelLoader, $instanceof, $file, $file, $this->environment));

        $extensions = [];
        foreach ($this->bundles as $bundle) {
            if ($extension = $bundle->getContainerExtension()) {
                $container->registerExtension($extension);
            }

            if ($this->debug) {
                $container->addObjectResource($bundle);
            }
        }

        foreach ($this->bundles as $bundle) {
            $bundle->build($container);
        }

        $this->build($container);

        foreach ($container->getExtensions() as $extension) {
            $extensions[] = $extension->getAlias();
        }

        if ([] !== $extensions) {
            // ensure these extensions are implicitly loaded
            $container->getCompilerPassConfig()->setMergePass(new MergeExtensionConfigurationPass($extensions));
        }

        $container->compile();

        //dump the container
        @\mkdir(\dirname($containerDumpFile), 0777, true);
        \file_put_contents(
            $containerDumpFile,
            (new PhpDumper($container))->dump(['class' => 'CachedContainer'])
        );
    }
}

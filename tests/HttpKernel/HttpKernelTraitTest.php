<?php

/*
 * This is part of the symfonette/neon-integration package.
 *
 * (c) Martin Hasoň <martin.hason@gmail.com>
 * (c) Webuni s.r.o. <info@webuni.cz>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfonette\NeonIntegration\Tests\HttpKernel;

use Symfonette\NeonIntegration\HttpKernel\NeonContainerLoaderTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Kernel;

class HttpKernelTraitTest extends \PHPUnit_Framework_TestCase
{
    public function testRegisterNeonLoader()
    {
        $kernel = new AppKernel('dev', true);
        $loader = $kernel->getLoader(new ContainerBuilder());
        $this->assertTrue($loader->supports('foo.neon'));
    }
}

class AppKernel extends Kernel
{
    use NeonContainerLoaderTrait;

    public function registerBundles()
    {
        return [];
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
    }

    public function getLoader(ContainerInterface $container)
    {
        return $this->getContainerLoader($container);
    }

    public function getCacheDir()
    {
        return sys_get_temp_dir();
    }
}

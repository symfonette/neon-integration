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

namespace Symfonette\NeonIntegration\HttpKernel;

use Symfonette\NeonIntegration\DependencyInjection\NeonFileLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Config\FileLocator;

trait NeonContainerLoaderTrait
{
    protected function getContainerLoader(ContainerInterface $container)
    {
        /* @var $loader \Symfony\Component\Config\Loader\Loader */
        $loader = parent::getContainerLoader($container);
        $resolver = $loader->getResolver();
        $resolver->addLoader(new NeonFileLoader($container, new FileLocator($this)));

        return $loader;
    }
}

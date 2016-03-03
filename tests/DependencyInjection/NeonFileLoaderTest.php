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

namespace Symfonette\NeonIntegration\tests\DependencyInjection;

use Symfonette\NeonIntegration\DependencyInjection\NeonFileLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;

class NeonFileLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ContainerBuilder
     */
    private $container;

    /**
     * @var NeonFileLoader
     */
    private $loader;

    protected function setUp()
    {
        $this->container = new ContainerBuilder();
        $this->loader = new NeonFileLoader($this->container, new FileLocator(__DIR__.'/fixtures'));
    }

    public function testSupportedResources()
    {
        $this->assertTrue($this->loader->supports('config.neon'));
        $this->assertFalse($this->loader->supports('config.yaml'));
        $this->assertFalse($this->loader->supports('config.neon', 'yaml'));
        $this->assertTrue($this->loader->supports('config.neon', 'neon'));
    }

    public function testClass()
    {
        $this->loader->load('class.neon');
        $this->assertEquals(new Definition('App\Users\Authenticator'), $this->get('authenticator'));
        $this->assertEquals(new Definition('App\EntityManager', ['slave']), $this->get('slave_entity_manager'));
    }

    public function testFactory()
    {
        $this->loader->load('factory.neon');
        $this->assertEquals(['App\Http\RouterFactory', 'createRouter'], $this->get('router')->getFactory());
        $this->assertEquals([new Reference('validator_factory'), 'createValidator'], $this->get('validator')->getFactory());
        $this->assertEquals([new Reference('doctrine'), 'getManager'], $this->get('entity_manager')->getFactory());
        $this->assertEquals(['cs'], $this->get('translator')->getArguments());
        $this->assertEquals(['registration'], $this->get('form')->getArguments());
    }

    public function testAlias()
    {
        $this->loader->load('alias.neon');
        $this->assertEquals(new Alias('translator_default'), $this->container->getAlias('translator'));
    }

    public function testParent()
    {
        $this->loader->load('parent.neon');

        $this->assertTrue($this->container->has('newsletter_manager'));
        $this->assertInstanceOf(DefinitionDecorator::class, $this->get('newsletter_manager'));
        $this->assertEquals('mail_manager', $this->get('newsletter_manager')->getParent());
    }

    public function testAutowire()
    {
        $this->loader->load('autowire.neon');
        $this->assertTrue($this->get('translator')->isAutowired());
        $this->assertEquals([''], $this->get('translator')->getArguments());

        $this->assertTrue($this->get('event_dispatcher')->isAutowired());
        $this->assertEquals(['', true], $this->get('event_dispatcher')->getArguments());

        $this->assertTrue($this->get('validator')->isAutowired());
        $this->assertEquals(['', true], $this->get('validator')->getArguments());

        $this->assertTrue($this->get('form')->isAutowired());
        $this->assertEquals(['', 'registration'], $this->get('form')->getArguments());
    }

    public function testCalls()
    {
        $this->loader->load('calls.neon');

        $this->assertEquals(
            [['setMailer', [new Reference('my_mailer')]], ['enableDebug', []]],
            $this->get('newsletter_manager')->getMethodCalls()
        );
    }

    public function testSetup()
    {
        $this->loader->load('setup.neon');

        $db = $this->get('database');
        $this->assertEquals(['substitutions' => ['%database.substitutions%']], $db->getProperties());
        $this->assertEquals([['setCacheStorage', []]], $db->getMethodCalls());
    }

    public function testTags()
    {
        $this->loader->load('tags.neon');

        $this->assertEquals(['kernel.event_listener' => [
            ['event' => 'kernel.response', 'method' => 'onKernelResponse'],
        ]], $this->get('app.response_listener')->getTags());
        $this->assertEquals(['form.type' => [[]]], $this->get('captcha_type')->getTags());
    }

    public function testExpression()
    {
        $this->loader->load('expression.neon');

        $definition = $this->get('my_mailer');
        $this->assertEquals([new Expression("service('mailer_configuration').getMailerMethod()")], $definition->getArguments());
        $this->assertEquals([['setLogger', [new Expression("service('logger')")]]], $definition->getMethodCalls());
    }

    public function testYamlCompatibility()
    {
        $this->loader->load('services_complete.neon');
    }

    /**
     * @return Definition|DefinitionDecorator
     */
    private function get($service)
    {
        return $this->container->getDefinition($service);
    }
}

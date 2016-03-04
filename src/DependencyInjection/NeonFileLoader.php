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

namespace Symfonette\NeonIntegration\DependencyInjection;

use Nette\Neon\Entity;
use Nette\Neon\Neon;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\FileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;

class NeonFileLoader extends FileLoader
{
    private static $keywords = [
        'alias' => 'alias',
        'parent' => 'parent',
        'class' => 'class',
        'shared' => 'shared',
        'synthetic' => 'synthetic',
        'lazy' => 'lazy',
        'public' => 'public',
        'abstract' => 'abstract',
        'deprecated' => 'deprecated',
        'factory' => 'factory',
        'file' => 'file',
        'arguments' => 'arguments',
        'properties' => 'properties',
        'configurator' => 'configurator',
        'calls' => 'calls',
        'tags' => 'tags',
        'decorates' => 'decorates',
        'decoration_inner_name' => 'decoration_inner_name',
        'decoration_priority' => 'decoration_priority',
        'autowire' => 'autowire',
        'autowiring_types' => 'autowiring_types',
        'setup' => 'setup', // nette
    ];

    /**
     * {@inheritdoc}
     */
    public function supports($resource, $type = null)
    {
        return is_string($resource) && 'neon' === pathinfo($resource, PATHINFO_EXTENSION) && (!$type || 'neon' === $type);
    }

    /**
     * {@inheritdoc}
     */
    public function load($resource, $type = null)
    {
        $path = $this->locator->locate($resource);

        $content = $this->loadFile($path);

        $this->container->addResource(new FileResource($path));

        if (null === $content) {
            return;
        }

        $this->parseImports($content, $path);

        if (isset($content['parameters'])) {
            if (!is_array($content['parameters'])) {
                throw new InvalidArgumentException(sprintf('The "parameters" key should contain an array in %s. Check your NEON syntax.', $resource));
            }

            foreach ($content['parameters'] as $key => $value) {
                $this->container->setParameter($key, $this->resolveServices($value));
            }
        }

        $this->loadFromExtensions($content);

        $this->parseDefinitions($content, $resource);
    }

    private function loadFile($file)
    {
        if (!class_exists('Nette\Neon\Neon')) {
            throw new RuntimeException('Unable to load NEON config files as the Nette Neon Component is not installed.');
        }

        if (!stream_is_local($file)) {
            throw new InvalidArgumentException(sprintf('This is not a local file "%s".', $file));
        }

        if (!file_exists($file)) {
            throw new InvalidArgumentException(sprintf('The service file "%s" is not valid.', $file));
        }

        try {
            $configuration = Neon::decode(file_get_contents($file));
        } catch (\Exception $e) {
            throw new InvalidArgumentException(sprintf('The file "%s" does not contain valid NEON.', $file), 0, $e);
        }

        return $this->validate($configuration, $file);
    }

    private function validate($content, $file)
    {
        if (null === $content) {
            return $content;
        }

        if (!is_array($content)) {
            throw new InvalidArgumentException(sprintf('The service file "%s" is not valid. It should contain an array. Check your NEON syntax.', $file));
        }

        foreach ($content as $namespace => $data) {
            if (in_array($namespace, ['imports', 'parameters', 'services'])) {
                continue;
            }

            if (!$this->container->hasExtension($namespace)) {
                $extensionNamespaces = array_filter(array_map(
                    function (ExtensionInterface $ext) {
                        return $ext->getAlias();
                    },
                    $this->container->getExtensions()
                ));

                throw new InvalidArgumentException(sprintf(
                    'There is no extension able to load the configuration for "%s" (in %s). Looked for namespace "%s", found %s',
                    $namespace,
                    $file,
                    $namespace,
                    $extensionNamespaces ? sprintf('"%s"', implode('", "', $extensionNamespaces)) : 'none'
                ));
            }
        }

        return $content;
    }

    private function parseImports($content, $file)
    {
        // nette
        if (!isset($content['imports']) && !isset($content['includes'])) {
            return;
        }

        // nette
        if (isset($content['imports']) && isset($content['includes'])) {
            throw new InvalidArgumentException('The "imports" and "includes" keys cannot be used together. Checkyour NEON syntax.', $file);
        }

        if (isset($content['imports']) && !is_array($content['imports'])) {
            throw new InvalidArgumentException(sprintf('The "imports" key should contain an array in %s. Check your NEON syntax.', $file));
        }

        // nette
        if (isset($content['includes']) && !is_array($content['includes'])) {
            throw new InvalidArgumentException(sprintf('The "includes" key should contain an array in %s. Check your NEON syntax.', $file));
        }

        // nette
        $content = array_merge(['imports' => [], 'includes' => []], $content);

        foreach ($content['imports'] as $import) {
            if (!is_array($import)) {
                throw new InvalidArgumentException(sprintf('The values in the "imports" key should be arrays in %s. Check your NEON syntax.', $file));
            }

            $this->setCurrentDir(dirname($file));
            $this->import($import['resource'], null, isset($import['ignore_errors']) ? (bool) $import['ignore_errors'] : false, $file);
        }

        // nette
        foreach ($content['includes'] as $include) {
            $this->setCurrentDir(dirname($file));
            $this->import($include, null, false, $file);
        }
    }

    private function parseDefinitions($content, $file)
    {
        if (!isset($content['services'])) {
            return;
        }

        if (!is_array($content['services'])) {
            throw new InvalidArgumentException(sprintf('The "services" key should contain an array in %s. Check your YAML syntax.', $file));
        }

        foreach ($content['services'] as $id => $service) {
            $this->parseDefinition($id, $service, $file);
        }
    }

    private function parseDefinition($id, $service, $file)
    {
        // nette
        if ($service instanceof Entity) {
            $value = $service->value;
            $service = ['arguments' => $service->attributes];
            if (false === strpos($value, ':')) {
                $service['class'] = $value;
            } else {
                $service['factory'] = $value;
            }
        }

        // nette
        if (preg_match('#^(\S+)\s+<\s+(\S+)\z#', $id, $matches)) {
            if (isset($service['parent']) && $matches[2] !== $service['parent']) {
                throw new InvalidArgumentException(sprintf('Two parent services "%s" and "%s" are defined for service "%s" in "%s". Check your NEON syntax.', $service['parent'], $matches[2], $matches[1], $file));
            }

            $id = $matches[1];
            $parent = $matches[2];
        }

        // nette
        if (is_string($service) && false !== strpos($service, ':')) {
            $service = ['factory' => $this->parseFactory($service)];
        } elseif (is_string($service) && 0 === strpos($service, '@')) {
            $this->container->setAlias($id, substr($service, 1));

            return;
        } elseif (is_string($service)) {
            $service = ['class' => $service];
        }

        if (!is_array($service)) {
            throw new InvalidArgumentException(sprintf('A service definition must be an array or a string starting with "@" or a NEON entity but %s found for service "%s" in %s. Check your NEON syntax.', gettype($service), $id, $file));
        }

        self::checkDefinition($id, $service, $file);

        if (isset($service['alias'])) {
            $public = !array_key_exists('public', $service) || (bool) $service['public'];
            $this->container->setAlias($id, new Alias($service['alias'], $public));

            foreach ($service as $key => $value) {
                if (!in_array($key, ['alias', 'public'])) {
                    throw new InvalidArgumentException(sprintf('The configuration key "%s" is unsupported for alias definition "%s" in "%s". Allowed configuration keys are "alias" and "public".', $key, $id, $file));
                }
            }

            return;
        }

        // nette
        if (isset($parent)) {
            $service['parent'] = $parent;
        }

        if (isset($service['parent'])) {
            $definition = new DefinitionDecorator($service['parent']);
        } else {
            $definition = new Definition();
        }

        if (isset($service['class'])) {
            $class = $service['class'];

            // nette
            if ($class instanceof Entity) {
                if (isset($service['arguments']) && !empty($class->attributes)) {
                    throw new InvalidArgumentException(sprintf('Duplicated definition of arguments for service "%s" in "%s". Check you NEON syntax.', $id, $file));
                }

                $service['arguments'] = $class->attributes;
                $class = $class->value;
            }

            $definition->setClass($class);
        }

        if (isset($service['shared'])) {
            $definition->setShared($service['shared']);
        }

        if (isset($service['synthetic'])) {
            $definition->setSynthetic($service['synthetic']);
        }

        if (isset($service['lazy'])) {
            $definition->setLazy($service['lazy']);
        }

        if (isset($service['public'])) {
            $definition->setPublic($service['public']);
        }

        if (isset($service['abstract'])) {
            $definition->setAbstract($service['abstract']);
        }

        if (array_key_exists('deprecated', $service)) {
            $definition->setDeprecated(true, $service['deprecated']);
        }

        if (isset($service['factory'])) {
            $factory = $service['factory'];

            //nette
            if ($factory instanceof Entity) {
                if (isset($service['arguments']) && !empty($factory->attributes)) {
                    throw new InvalidArgumentException(sprintf('Duplicated definition of arguments for service "%s" in "%s". Check you NEON syntax.', $id, $file));
                }

                $service['arguments'] = $factory->attributes;
                $factory = $factory->value;
            }

            $definition->setFactory($this->parseFactory($factory));
        }

        if (isset($service['file'])) {
            $definition->setFile($service['file']);
        }

        if (isset($service['arguments'])) {
            $autowired = false;
            array_walk($service['arguments'], function (&$value) use (&$autowired) {
                if ('...' === $value) {
                    $value = '';
                    $autowired = true;
                }

                return $value;
            });

            $definition->setAutowired($autowired);
            $definition->setArguments($this->resolveServices($service['arguments']));
        }

        // nette
        if (isset($service['setup'])) {
            foreach ($service['setup'] as $setup) {
                if ($setup instanceof Entity) {
                    $name = $setup->value;
                    $args = $setup->attributes;
                } elseif (is_array($setup)) {
                    $name = $setup[0];
                    $args = isset($setup[1]) ? $setup[1] : [];
                } else {
                    $name = $setup;
                    $args = [];
                }

                if ('$' === $name[0]) {
                    $service['properties'][substr($name, 1)] = $args;
                } else {
                    $service['calls'][] = [$name, $args];
                }
            }
        }

        if (isset($service['properties'])) {
            $definition->setProperties($this->resolveServices($service['properties']));
        }

        if (isset($service['configurator'])) {
            if (is_string($service['configurator'])) {
                $definition->setConfigurator($service['configurator']);
            } else {
                $definition->setConfigurator([$this->resolveServices($service['configurator'][0]), $service['configurator'][1]]);
            }
        }

        if (isset($service['calls'])) {
            if (!is_array($service['calls'])) {
                throw new InvalidArgumentException(sprintf('Parameter "calls" must be an array for service "%s" in %s. Check your NEON syntax.', $id, $file));
            }

            foreach ($service['calls'] as $call) {
                if ($call instanceof Entity) { // nette
                    $method = $call->value;
                    $args = $this->resolveServices($call->attributes);
                } elseif (isset($call['method'])) {
                    $method = $call['method'];
                    $args = isset($call['arguments']) ? $this->resolveServices($call['arguments']) : [];
                } elseif (is_array($call)) {
                    $method = $call[0];
                    $args = isset($call[1]) ? $this->resolveServices($call[1]) : [];
                } else { // nette
                    $method = $call;
                    $args = [];
                }

                $definition->addMethodCall($method, $args);
            }
        }

        if (isset($service['tags'])) {
            if (!is_array($service['tags'])) {
                throw new InvalidArgumentException(sprintf('Parameter "tags" must be an array for service "%s" in %s. Check your NEON syntax.', $id, $file));
            }

            foreach ($service['tags'] as $tag) {
                if ($tag instanceof Entity) {
                    $tag = ['name' => $tag->value] + $tag->attributes;
                } elseif (is_string($tag)) {
                    $tag = ['name' => $tag];
                }

                if (!is_array($tag)) {
                    throw new InvalidArgumentException(sprintf('A "tags" entry must be an array for service "%s" in %s. Check your NEON syntax.', $id, $file));
                }

                if (!isset($tag['name'])) {
                    throw new InvalidArgumentException(sprintf('A "tags" entry is missing a "name" key for service "%s" in %s.', $id, $file));
                }

                if (!is_string($tag['name']) || '' === $tag['name']) {
                    throw new InvalidArgumentException(sprintf('The tag name for service "%s" in %s must be a non-empty string.', $id, $file));
                }

                $name = $tag['name'];
                unset($tag['name']);

                foreach ($tag as $attribute => $value) {
                    if (!is_scalar($value) && null !== $value) {
                        throw new InvalidArgumentException(sprintf('A "tags" attribute must be of a scalar-type for service "%s", tag "%s", attribute "%s" in %s. Check your NEON syntax.', $id, $name, $attribute, $file));
                    }
                }

                $definition->addTag($name, $tag);
            }
        }

        if (isset($service['decorates'])) {
            $renameId = isset($service['decoration_inner_name']) ? $service['decoration_inner_name'] : null;
            $priority = isset($service['decoration_priority']) ? $service['decoration_priority'] : 0;
            $definition->setDecoratedService($service['decorates'], $renameId, $priority);
        }

        // nette
        if (isset($service['autowired'])) {
            if (isset($service['autowire']) && $service['autowire'] !== $service['autowired']) {
                throw new InvalidArgumentException(sprintf('Contradictory definition of autowiring for service "%s" in "%s". Check you NEON syntax.', $id, $file));
            }

            $service['autowire'] = $service['autowired'];
        }

        if (isset($service['autowire'])) {
            // nette
            if ($definition->isAutowired() && !$service['autowire']) {
                throw new InvalidArgumentException(sprintf('Contradictory definition of autowiring for service "%s" in "%s". Check you NEON syntax.', $id, $file));
            }

            $definition->setAutowired($service['autowire']);
        }

        if (isset($service['autowiring_types'])) {
            if (is_string($service['autowiring_types'])) {
                $definition->addAutowiringType($service['autowiring_types']);
            } else {
                if (!is_array($service['autowiring_types'])) {
                    throw new InvalidArgumentException(sprintf('Parameter "autowiring_types" must be a string or an array for service "%s" in %s. Check your NEON syntax.', $id, $file));
                }

                foreach ($service['autowiring_types'] as $autowiringType) {
                    if (!is_string($autowiringType)) {
                        throw new InvalidArgumentException(sprintf('A "autowiring_types" attribute must be of type string for service "%s" in %s. Check your NEON syntax.', $id, $file));
                    }

                    $definition->addAutowiringType($autowiringType);
                }
            }
        }

        $this->container->setDefinition($id, $definition);
    }

    private function parseFactory($factory)
    {
        if (is_string($factory)) {
            if (strpos($factory, '::') !== false) {
                $parts = explode('::', $factory, 2);

                return ['@' === $parts[0][0] ? $this->resolveServices($parts[0]) : $parts[0], $parts[1]];
            } elseif (strpos($factory, ':') !== false) {
                $parts = explode(':', $factory, 2);

                return [$this->resolveServices(('@' === $parts[0][0] ?: '@').$parts[0]), $parts[1]];
            } else {
                return $factory;
            }
        } else {
            return [$this->resolveServices($factory[0]), $factory[1]];
        }
    }

    private function resolveServices($value)
    {
        // nette
        if ($value instanceof Entity) {
            if ('expression' === $value->value || 'expr' === $value->value) {
                return new Expression(reset($value->attributes));
            } elseif (0 === strpos($value->value, '@')) {
                $value = $value->value;
            }
        }

        if (is_array($value)) {
            $value = array_map([$this, 'resolveServices'], $value);
        } elseif (is_string($value) &&  0 === strpos($value, '@=')) {
            return new Expression(substr($value, 2));
        } elseif (is_string($value) &&  0 === strpos($value, '@')) {
            if (0 === strpos($value, '@@')) {
                $value = substr($value, 1);
                $invalidBehavior = null;
            } elseif (0 === strpos($value, '@?')) {
                $value = substr($value, 2);
                $invalidBehavior = ContainerInterface::IGNORE_ON_INVALID_REFERENCE;
            } else {
                $value = substr($value, 1);
                $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
            }

            if ('=' === substr($value, -1)) {
                $value = substr($value, 0, -1);
            }

            if (null !== $invalidBehavior) {
                $value = new Reference($value, $invalidBehavior);
            }
        }

        return $value;
    }

    private function loadFromExtensions($content)
    {
        foreach ($content as $namespace => $values) {
            // nette
            if (in_array($namespace, ['imports', 'includes', 'parameters', 'services'])) {
                continue;
            }

            if (!is_array($values)) {
                $values = [];
            }

            $this->container->loadFromExtension($namespace, $values);
        }
    }

    private static function checkDefinition($id, array $definition, $file)
    {
        foreach ($definition as $key => $value) {
            if (!isset(self::$keywords[$key])) {
                throw new InvalidArgumentException(sprintf('The configuration key "%s" is unsupported for service definition "%s" in "%s". Allowed configuration keys are "%s".', $key, $id, $file, implode('", "', self::$keywords)));
            }
        }
    }
}

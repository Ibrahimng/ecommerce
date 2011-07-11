<?php

/*
 * This file is part of the Sonata package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\PaymentBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;

/**
 * UrlShortenerExtension.
 *
 *
 * @author     Thomas Rabaix <thomas.rabaix@sonata-project.org>
 */
class SonataPaymentExtension extends Extension
{
    /**
     * Loads the delivery configuration.
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function load(array $config, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('orm.xml');
        $loader->load('payment.xml');
        $loader->load('generator.xml');
        $loader->load('transformer.xml');
        $loader->load('selector.xml');

        $config = call_user_func_array('array_merge_recursive', $config);

        if(isset($config['services'])) {
            $this->configurePayment($config['services'], $container);
        }

        if(isset($config['selector'])) {
            $this->configureSelector($config['selector'], $container);
        }

        if(isset($config['transformers'])) {
            $this->configureTransformer($config['transformers'], $container);
        }
    }

    public function configurePayment($services, ContainerBuilder $container)
    {
        // create the payment method pool
        $pool = $container->getDefinition('sonata.payment.pool');

        // define the payment method
        foreach ($services as $id => $settings) {
            $enabled  = isset($settings['enabled']) ? $settings['enabled'] : true;
            $name     = isset($settings['name']) ? $settings['name'] : 'n/a';
            $options  = isset($settings['options']) ? $settings['options'] : array();

            if (!$enabled) {
                $container->removeDefinition($id);
                continue;
            }

            $definition = $container->getDefinition($id);

            $definition->addMethodCall('setName', array($name));
            $definition->addMethodCall('setCode', array($id));
            $definition->addMethodCall('setEnabled', array($enabled));
            $definition->addMethodCall('setOptions', array($options));

            foreach ((array)$settings['transformers'] as $name => $serviceId) {
                $definition->addMethodCall('addTransformer', array($name, new Reference($serviceId)));
            }

            // add the delivery method in the method pool
            $pool->addMethodCall('addMethod', array(new Reference($id)));
        }
    }

    public function configureSelector($selector, ContainerBuilder $container)
    {
        // define the payment selector
        $definition = $container->getDefinition($selector);

        $container->setDefinition('sonata.payment.selector', $definition);
    }

    public function configureTransformer($transformers, ContainerBuilder $container)
    {
        $pool = $container->getDefinition('sonata.payment.transformer.pool');

        foreach ($transformers as $type => $id) {
            $pool->addMethodCall('addTransformer', array($type, new Reference($id)));
        }
    }

    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/../Resources/config/schema';
    }

    public function getNamespace()
    {
        return 'http://www.sonata-project.org/schema/dic/sonata-payment';
    }

    public function getAlias()
    {
        return 'sonata_payment';
    }
}
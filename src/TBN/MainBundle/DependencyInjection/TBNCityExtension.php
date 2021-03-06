<?php

namespace TBN\MainBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class TBNCityExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $this->addClassesToCompile([
           'TBN\\AgendaBundle\\Geolocalize',
           'TBN\\AgendaBundle\\AgendaRepository',
           'TBN\\MainBundle\\Listener',
           'TBN\\MainBundle\\Routing',
           'TBN\\MainBundle\\Site',
           'TBN\\MainBundle\\Twig',
           'TBN\\MajDataBundle\\Cleaner',
           'TBN\\MajDataBundle\\Fetcher',
           'TBN\\MajDataBundle\\Reject',
           'TBN\\MajDataBundle\\Utils',
           'TBN\\SocialBundle\\Exception',
           'TBN\\UserBundle\\Captcha',
           'TBN\\UserBundle\\EventListener',
           'TBN\\UserBundle\\Handler',
           'TBN\\UserBundle\\Validator\\Constraints',
           'TBN\\SocialBundle\\Social',
           'TBN\\**Bundle\\Repository',
           'TBN\\**Bundle\\Parser',
        ]);

        $this->addAnnotatedClassesToCompile([
            'TBN\\AgendaBundle\\Search',
        ]);
    }
}

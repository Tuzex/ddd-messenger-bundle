<?php

declare(strict_types=1);

namespace Tuzex\Bundle\Ddd\Messenger\DependencyInjection;

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Tuzex\Ddd\Application\Domain\DomainEventHandler;
use Tuzex\Ddd\Infrastructure\Integration\IntegrationEventHandler;

final class DddMessengerExtension extends Extension implements PrependExtensionInterface
{
    private FileLocator $fileLocator;

    public function __construct()
    {
        $this->fileLocator = new FileLocator(__DIR__.'/../Resources/config');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->setUpMessengerBuses($container);
        $this->setUpDoctrineTypes($container);
        $this->registerHandlersForAutoconfiguration($container);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, $this->fileLocator);
        $loader->load('services.xml');
    }

    private function setUpMessengerBuses(ContainerBuilder $container): void
    {
        $configuration = new Configuration(false);
        $configs = $this->processConfiguration($configuration, $container->getExtensionConfig('framework'));

        $container->prependExtensionConfig('framework', [
            'messenger' => [
                'default_bus' => $configs['messenger']['default_bus'] ?? 'tuzex.ddd.integration_event_bus',
                'buses' => [
                    'tuzex.ddd.domain_event_bus' => [
                        'default_middleware' => 'allow_no_handlers',
                    ],
                    'tuzex.ddd.integration_event_bus' => [
                        'default_middleware' => 'allow_no_handlers',
                    ],
                ],
            ],
        ]);
    }

    private function setUpDoctrineTypes(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('doctrine', [
            'dbal' => [
                'types' => [
                    'tuzex.instant' => 'Tuzex\Ddd\Infrastructure\Persistence\Doctrine\Dbal\Type\InstantType',
                    'tuzex.date_time' => 'Tuzex\Ddd\Infrastructure\Persistence\Doctrine\Dbal\Type\DateTime\DateTimeType',
                    'tuzex.uid' => 'Tuzex\Ddd\Infrastructure\Persistence\Doctrine\Dbal\Type\Id\UniversalIdType',
                ],
            ],
        ]);
    }

    private function registerHandlersForAutoconfiguration(ContainerBuilder $container): void
    {
        $services = [
            DomainEventHandler::class => 'domain_event',
            IntegrationEventHandler::class => 'integration_event',
        ];

        foreach ($services as $interface => $service) {
            $container->registerForAutoconfiguration($interface)
                ->addTag(sprintf('tuzex.ddd.%s_handler', $service))
                ->addTag('messenger.message_handler', [
                    'bus' => sprintf('tuzex.ddd.%s_bus', $service),
                ]);
        }
    }
}

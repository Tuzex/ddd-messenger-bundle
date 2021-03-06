<?php

declare(strict_types=1);

namespace Tuzex\Bundle\Ddd\Messenger\Test\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tuzex\Bundle\Ddd\Messenger\DependencyInjection\DddMessengerExtension;
use Tuzex\Ddd\Application\Domain\DomainEventHandler;
use Tuzex\Ddd\Application\DomainEventBus;
use Tuzex\Ddd\Domain\Clock;
use Tuzex\Ddd\Domain\Id\UniversalIds;
use Tuzex\Ddd\Infrastructure\Domain\Clock\SystemClock;
use Tuzex\Ddd\Infrastructure\Domain\Id\UniversalIdFactory;
use Tuzex\Ddd\Infrastructure\Integration\IntegrationEventBus;
use Tuzex\Ddd\Infrastructure\Integration\IntegrationEventHandler;
use Tuzex\Ddd\Infrastructure\Support\Symfony\Uuid\UuidFactory;
use Tuzex\Ddd\Infrastructure\Support\Symfony\Uuid\UuidFactory\UuidV3Factory;
use Tuzex\Ddd\Infrastructure\Support\Symfony\Uuid\UuidFactory\UuidV5Factory;
use Tuzex\Ddd\Infrastructure\Support\Symfony\Uuid\UuidGenerator;
use Tuzex\Ddd\Infrastructure\Support\Symfony\Uuid\UuidGenerator\UuidV4Generator;
use Tuzex\Ddd\Messenger\MessengerDomainEventBus;
use Tuzex\Ddd\Messenger\MessengerIntegrationEventBus;
use Tuzex\Timekeeper\SystemTimeService;
use Tuzex\Timekeeper\TimeService;

final class DddMessengerExtensionTest extends TestCase
{
    private DddMessengerExtension $dddExtension;
    private ContainerBuilder $containerBuilder;

    protected function setUp(): void
    {
        $this->dddExtension = new DddMessengerExtension();
        $this->containerBuilder = new ContainerBuilder();

        parent::setUp();
    }

    public function testItContainsMessengerConfigs(): void
    {
        $this->dddExtension->prepend($this->containerBuilder);

        $messengerConfigs = $this->resolveMessengerConfig();

        $this->assertArrayHasKey('default_bus', $messengerConfigs);
        $this->assertArrayHasKey('buses', $messengerConfigs);
    }

    /**
     * @dataProvider provideBusIds
     */
    public function testItRegistersExpectedBuses(string $busId): void
    {
        $this->dddExtension->prepend($this->containerBuilder);

        $this->assertArrayHasKey($busId, $this->resolveMessengerConfig()['buses']);
    }

    public function provideBusIds(): array
    {
        return [
            'domainEventBus' => [
                'busId' => 'tuzex.ddd.domain_event_bus',
            ],
            'integrationEventBus' => [
                'busId' => 'tuzex.ddd.integration_event_bus',
            ],
        ];
    }

    /**
     * @dataProvider provideTypeDefinitions
     */
    public function testItRegistersExpectedTypes(string $typeName, string $typeClass): void
    {
        $this->dddExtension->prepend($this->containerBuilder);
        $registeredTypes = $this->resolveDoctrineConfig()['dbal']['types'];

        $this->assertArrayHasKey($typeName, $registeredTypes);
        $this->assertContains($typeClass, $registeredTypes);
    }

    public function provideTypeDefinitions(): array
    {
        return [
            'instantType' => [
                'typeName' => 'tuzex.instant',
                'typeClass' => 'Tuzex\Ddd\Infrastructure\Persistence\Doctrine\Dbal\Type\InstantType',
            ],
            'dateTimeType' => [
                'typeName' => 'tuzex.date_time',
                'typeClass' => 'Tuzex\Ddd\Infrastructure\Persistence\Doctrine\Dbal\Type\DateTime\DateTimeType',
            ],
            'universalIdType' => [
                'typeName' => 'tuzex.uid',
                'typeClass' => 'Tuzex\Ddd\Infrastructure\Persistence\Doctrine\Dbal\Type\Id\UniversalIdType',
            ],
        ];
    }

    /**
     * @dataProvider provideHandlerSettings
     */
    public function testItRegistersAutoconfigurationOfHandlers(string $id): void
    {
        $this->dddExtension->prepend($this->containerBuilder);

        $this->assertArrayHasKey($id, $this->containerBuilder->getAutoconfiguredInstanceof());
    }

    /**
     * @dataProvider provideHandlerSettings
     */
    public function testItSetsAutoconfigurationTags(string $id, array $tags): void
    {
        $this->dddExtension->prepend($this->containerBuilder);

        $autoconfiguration = $this->containerBuilder->getAutoconfiguredInstanceof()[$id];

        foreach ($tags as $tag => $configs) {
            $this->assertArrayHasKey($tag, $autoconfiguration->getTags());
            $this->assertContainsEquals($configs, $autoconfiguration->getTags()[$tag]);
        }
    }

    public function provideHandlerSettings(): array
    {
        return [
            'domainEventHandler' => [
                'id' => DomainEventHandler::class,
                'tags' => [
                    'tuzex.ddd.domain_event_handler' => [],
                    'messenger.message_handler' => [
                        'bus' => 'tuzex.ddd.domain_event_bus',
                    ],
                ],
            ],
            'integrationEventHandler' => [
                'id' => IntegrationEventHandler::class,
                'tags' => [
                    'tuzex.ddd.integration_event_handler' => [],
                    'messenger.message_handler' => [
                        'bus' => 'tuzex.ddd.integration_event_bus',
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideServiceIds
     */
    public function testItRegistersExpectedServices(string $serviceId): void
    {
        $this->dddExtension->load([], $this->containerBuilder);

        $this->assertTrue($this->containerBuilder->hasDefinition($serviceId));
    }

    public function provideServiceIds(): iterable
    {
        $serviceIds = [
            'clock' => SystemClock::class,
            'universalIds' => UniversalIdFactory::class,
            'integrationEventBus' => MessengerIntegrationEventBus::class,
            'domainEventBus' => MessengerDomainEventBus::class,
            'timeService' => SystemTimeService::class,
            'uuidV4Generator' => UuidV4Generator::class,
            'uuidV3Factory' => UuidV3Factory::class,
            'uuidV5Factory' => UuidV5Factory::class,
        ];

        foreach ($serviceIds as $serviceAlias => $serviceId) {
            yield $serviceAlias => [
                'serviceId' => $serviceId,
            ];
        }
    }

    /**
     * @dataProvider provideServiceAliases
     */
    public function testItRegistersAliases(string $serviceAlias, string $serviceId): void
    {
        $this->dddExtension->load([], $this->containerBuilder);

        $this->assertSame($serviceId, (string) $this->containerBuilder->getAlias($serviceAlias));
    }

    public function provideServiceAliases(): iterable
    {
        $serviceAliases = [
            Clock::class => SystemClock::class,
            UniversalIds::class => UniversalIdFactory::class,
            IntegrationEventBus::class => MessengerIntegrationEventBus::class,
            DomainEventBus::class => MessengerDomainEventBus::class,
            TimeService::class => SystemTimeService::class,
            UuidGenerator::class => UuidV4Generator::class,
            UuidFactory::class => UuidV5Factory::class,
        ];

        foreach ($serviceAliases as $serviceAlias => $serviceId) {
            yield $serviceAlias => [
                'serviceAlias' => $serviceAlias,
                'serviceId' => $serviceId,
            ];
        }
    }

    private function resolveMessengerConfig(): array
    {
        return $this->resolveFrameworkConfig()['messenger'];
    }

    private function resolveDoctrineConfig(): array
    {
        return $this->containerBuilder->getExtensionConfig('doctrine')[0];
    }

    private function resolveFrameworkConfig(): array
    {
        return $this->containerBuilder->getExtensionConfig('framework')[0];
    }
}

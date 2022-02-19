<?php

declare(strict_types=1);

namespace Tuzex\Bundle\Ddd\Test\Messenger\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tuzex\Bundle\Ddd\Messenger\DependencyInjection\DddMessengerExtension;
use Tuzex\Ddd\Application\Domain\DomainCommandHandler;
use Tuzex\Ddd\Application\Domain\DomainEventHandler;
use Tuzex\Ddd\Application\DomainCommandBus;
use Tuzex\Ddd\Application\DomainEventBus;
use Tuzex\Ddd\Domain\Clock;
use Tuzex\Ddd\Infrastructure\Domain\Clock\SystemClock;
use Tuzex\Ddd\Messenger\MessengerDomainCommandBus;
use Tuzex\Ddd\Messenger\MessengerDomainEventBus;
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
            'domainCommandBus' => [
                'busId' => 'tuzex.ddd.domain_command_bus',
            ],
            'domainEventBus' => [
                'busId' => 'tuzex.ddd.domain_event_bus',
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
            'commandHandler' => [
                'id' => DomainCommandHandler::class,
                'tags' => [
                    'tuzex.ddd.domain_command_handler' => [],
                    'messenger.message_handler' => [
                        'bus' => 'tuzex.ddd.domain_command_bus',
                    ],
                ],
            ],
            'domainEventHandler' => [
                'id' => DomainEventHandler::class,
                'tags' => [
                    'tuzex.ddd.domain_event_handler' => [],
                    'messenger.message_handler' => [
                        'bus' => 'tuzex.ddd.domain_event_bus',
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
            'domainCommandBus' => MessengerDomainCommandBus::class,
            'domainEventBus' => MessengerDomainEventBus::class,
            'timeService' => SystemTimeService::class,
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
            DomainCommandBus::class => MessengerDomainCommandBus::class,
            DomainEventBus::class => MessengerDomainEventBus::class,
            TimeService::class => SystemTimeService::class,
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

    private function resolveFrameworkConfig(): array
    {
        return $this->containerBuilder->getExtensionConfig('framework')[0];
    }
}

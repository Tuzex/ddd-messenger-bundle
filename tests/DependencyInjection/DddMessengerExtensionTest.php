<?php

declare(strict_types=1);

namespace Tuzex\Bundle\Ddd\Test\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tuzex\Bundle\Ddd\DependencyInjection\DddMessengerExtension;
use Tuzex\Bundle\Ddd\Messaging\MessengerCommandBus;
use Tuzex\Bundle\Ddd\Messaging\MessengerDomainEventBus;
use Tuzex\Ddd\Application\CommandBus;
use Tuzex\Ddd\Application\DomainEventBus;
use Tuzex\Ddd\Application\Entrypoint\CommandHandler;
use Tuzex\Ddd\Application\Entrypoint\DomainEventHandler;
use Tuzex\Ddd\Application\Service\CommandsSpooler;
use Tuzex\Ddd\Application\Service\DirectCommandsSpooler;
use Tuzex\Ddd\Application\Service\DirectDomainEventsPublisher;
use Tuzex\Ddd\Application\Service\DomainEventsPublisher;

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
            'command-bus' => [
                'busId' => 'tuzex.ddd.command_bus',
            ],
            'domain-event-bus' => [
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
            'command-handler' => [
                'id' => CommandHandler::class,
                'tags' => [
                    'tuzex.ddd.command_handler' => [],
                    'messenger.message_handler' => [
                        'bus' => 'tuzex.ddd.command_bus',
                    ],
                ],
            ],
            'domain-event-handler' => [
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

    public function provideServiceIds(): array
    {
        return [
            'command-bus' => [
                'serviceId' => MessengerCommandBus::class,
            ],
            'domain-event-bus' => [
                'serviceId' => MessengerDomainEventBus::class,
            ],
            'commands-spooler' => [
                'serviceId' => DirectCommandsSpooler::class,
            ],
            'domain-events-publisher' => [
                'serviceId' => DirectDomainEventsPublisher::class,
            ],
        ];
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
            CommandBus::class => MessengerCommandBus::class,
            CommandsSpooler::class => DirectCommandsSpooler::class,
            DomainEventBus::class => MessengerDomainEventBus::class,
            DomainEventsPublisher::class => DirectDomainEventsPublisher::class,
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

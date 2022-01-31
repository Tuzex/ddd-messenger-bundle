<?php

declare(strict_types=1);

namespace Tuzex\Bundle\Ddd\Test\Messenger\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tuzex\Bundle\Ddd\Messenger\DependencyInjection\DddMessengerExtension;
use Tuzex\Ddd\Core\Application\Domain\DomainCommandHandler;
use Tuzex\Ddd\Core\Application\Domain\DomainEventHandler;
use Tuzex\Ddd\Core\Application\DomainCommandBus;
use Tuzex\Ddd\Core\Application\DomainEventBus;
use Tuzex\Ddd\Core\Application\Service\DirectDomainCommandsDispatcher;
use Tuzex\Ddd\Core\Application\Service\DirectDomainEventsPublisher;
use Tuzex\Ddd\Core\Application\Service\DomainCommandsDispatcher;
use Tuzex\Ddd\Core\Application\Service\DomainEventsPublisher;
use Tuzex\Ddd\Messenger\MessengerDomainCommandBus;
use Tuzex\Ddd\Messenger\MessengerDomainEventBus;

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
            'domain-command-bus' => [
                'busId' => 'tuzex.ddd.domain_command_bus',
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
                'id' => DomainCommandHandler::class,
                'tags' => [
                    'tuzex.ddd.domain_command_handler' => [],
                    'messenger.message_handler' => [
                        'bus' => 'tuzex.ddd.domain_command_bus',
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
            'domain-command-bus' => [
                'serviceId' => MessengerDomainCommandBus::class,
            ],
            'domain-event-bus' => [
                'serviceId' => MessengerDomainEventBus::class,
            ],
            'domain-commands-dispatcher' => [
                'serviceId' => DirectDomainCommandsDispatcher::class,
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
            DomainCommandBus::class => MessengerDomainCommandBus::class,
            DomainCommandsDispatcher::class => DirectDomainCommandsDispatcher::class,
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

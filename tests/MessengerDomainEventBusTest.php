<?php

declare(strict_types=1);

namespace Tuzex\Bundle\Ddd\Test\Messenger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Tuzex\Bundle\Ddd\Messenger\MessengerDomainEventBus;
use Tuzex\Ddd\Core\Domain\DomainEvent;

final class MessengerDomainEventBusTest extends TestCase
{
    public function testItDispatchesDomainEventToMessageBus(): void
    {
        $domainEvent = $this->createMock(DomainEvent::class);
        $domainEventBus = new MessengerDomainEventBus($this->mockMessageBus($domainEvent));

        $domainEventBus->publish($domainEvent);
    }

    private function mockMessageBus(DomainEvent $domainEvent): MessageBusInterface
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(
                new Envelope($domainEvent)
            );

        return $messageBus;
    }
}

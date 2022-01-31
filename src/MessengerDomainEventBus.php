<?php

declare(strict_types=1);

namespace Tuzex\Bundle\Ddd\Messenger;

use Symfony\Component\Messenger\MessageBusInterface;
use Tuzex\Ddd\Core\Application\DomainEventBus;
use Tuzex\Ddd\Core\Domain\DomainEvent;

final class MessengerDomainEventBus implements DomainEventBus
{
    public function __construct(
        private MessageBusInterface $messageBus
    ) {}

    public function publish(DomainEvent $domainEvent): void
    {
        $this->messageBus->dispatch($domainEvent);
    }
}

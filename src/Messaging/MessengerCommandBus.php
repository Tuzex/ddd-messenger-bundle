<?php

declare(strict_types=1);

namespace Tuzex\Bundle\Ddd\Messaging;

use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\MessageBusInterface;
use Tuzex\Bundle\Ddd\Messaging\Exception\CommandHandlerNotFoundException;
use Tuzex\Ddd\Application\CommandBus;
use Tuzex\Ddd\Domain\Command;

final class MessengerCommandBus implements CommandBus
{
    public function __construct(
        private MessageBusInterface $messageBus
    ) {}

    public function execute(Command $command): void
    {
        try {
            $this->messageBus->dispatch($command);
        } catch (NoHandlerForMessageException $exception) {
            throw new CommandHandlerNotFoundException($command, $exception);
        }
    }
}

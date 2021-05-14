<?php

declare(strict_types=1);

namespace Tuzex\Bundle\Ddd\Test\Messaging;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\MessageBusInterface;
use Tuzex\Bundle\Ddd\Messaging\Exception\CommandHandlerNotFoundException;
use Tuzex\Bundle\Ddd\Messaging\MessengerCommandBus;
use Tuzex\Ddd\Domain\Command;

final class MessengerCommandBusTest extends TestCase
{
    public function testItDispatchesCommandToMessageBus(): void
    {
        $command = $this->createMock(Command::class);
        $commandBus = new MessengerCommandBus($this->mockMessageBus($command));

        $commandBus->execute($command);
    }

    public function testItThrowsExceptionIfCommandHandlerNotExists(): void
    {
        $command = $this->createMock(Command::class);
        $commandBus = new MessengerCommandBus($this->mockMessageBus($command, false));

        $this->expectException(CommandHandlerNotFoundException::class);
        $commandBus->execute($command);
    }

    private function mockMessageBus(Command $command, bool $handle = true): MessageBusInterface
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $dispatchMethod = $messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(
                new Envelope($command)
            );

        if (! $handle) {
            $dispatchMethod->willThrowException(new NoHandlerForMessageException());
        }

        return $messageBus;
    }
}

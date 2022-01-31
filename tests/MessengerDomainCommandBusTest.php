<?php

declare(strict_types=1);

namespace Tuzex\Bundle\Ddd\Test\Messenger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\MessageBusInterface;
use Tuzex\Bundle\Ddd\Messenger\Exception\NoHandlerForDomainCommandException;
use Tuzex\Bundle\Ddd\Messenger\MessengerDomainCommandBus;
use Tuzex\Ddd\Core\Domain\DomainCommand;

final class MessengerDomainCommandBusTest extends TestCase
{
    public function testItDispatchesCommandToMessageBus(): void
    {
        $domainCommand = $this->mockDomainCommand();
        $domainCommandBus = new MessengerDomainCommandBus($this->mockMessageBus($domainCommand));

        $domainCommandBus->dispatch($domainCommand);
    }

    public function testItThrowsExceptionIfCommandHandlerNotExists(): void
    {
        $domainCommand = $this->mockDomainCommand();
        $domainCommandBus = new MessengerDomainCommandBus($this->mockMessageBus($domainCommand, false));

        $this->expectException(NoHandlerForDomainCommandException::class);
        $domainCommandBus->dispatch($domainCommand);
    }

    private function mockDomainCommand(): DomainCommand
    {
        return $this->createMock(DomainCommand::class);
    }

    private function mockMessageBus(DomainCommand $domainCommand, bool $handle = true): MessageBusInterface
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $dispatchMethod = $messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(
                new Envelope($domainCommand)
            );

        if (! $handle) {
            $dispatchMethod->willThrowException(new NoHandlerForMessageException());
        }

        return $messageBus;
    }
}

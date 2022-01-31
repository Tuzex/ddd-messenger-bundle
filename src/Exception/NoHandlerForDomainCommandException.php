<?php

declare(strict_types=1);

namespace Tuzex\Bundle\Ddd\Messenger\Exception;

use LogicException;
use Throwable;
use Tuzex\Ddd\Core\Domain\DomainCommand;

final class NoHandlerForDomainCommandException extends LogicException
{
    public function __construct(DomainCommand $domainCommand, Throwable $previous)
    {
        parent::__construct(sprintf('Handler for domain command "%s" not found.', $domainCommand::class), $previous->getCode(), $previous);
    }
}

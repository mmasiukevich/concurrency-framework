<?php

/**
 * PHP Service Bus (publish-subscribe pattern implementation)
 * Supports Saga pattern and Event Sourcing
 *
 * @author  Maksim Masiukevich <desperado@minsk-info.ru>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace Desperado\ServiceBus\Tests\Stubs\Context;

use Amp\Promise;
use Amp\Success;
use Desperado\ServiceBus\Common\Contract\Messages\Command;
use Desperado\ServiceBus\Common\Contract\Messages\Event;
use Desperado\ServiceBus\Common\Contract\Messages\Message;
use Desperado\ServiceBus\Common\ExecutionContext\LoggingInContext;
use Desperado\ServiceBus\Common\ExecutionContext\MessageDeliveryContext;
use Desperado\ServiceBus\Infrastructure\Transport\SendOptions;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 *
 */
final class TestContext implements MessageDeliveryContext, LoggingInContext
{
    public $messages = [];

    /**
     * @var TestHandler
     */
    public $testLogHandler;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct()
    {
        $this->testLogHandler = new TestHandler();
        $this->logger         = new Logger(
            __CLASS__,
            [$this->testLogHandler]
        );
    }

    /**
     * @inheritdoc
     */
    public function delivery(Message ...$messages): Promise
    {
        $this->messages = \array_merge($messages, $this->messages);

        return new Success();
    }

    /**
     * @inheritDoc
     */
    public function send(Command $command, SendOptions $options): Promise
    {
        $this->messages[] = $command;

        return new Success();
    }

    /**
     * @inheritDoc
     */
    public function publish(Event $event, SendOptions $options): Promise
    {
        $this->messages[] = $event;

        return new Success();
    }

    /**
     * @inheritDoc
     */
    public function logContextMessage(string $logMessage, array $extra = [], string $level = LogLevel::INFO): void
    {
        $this->logger->log($level, $logMessage, $extra);
    }

    /**
     * @inheritDoc
     */
    public function logContextThrowable(\Throwable $throwable, string $level = LogLevel::ERROR, array $extra = []): void
    {
        $this->logContextMessage($throwable->getMessage());
    }
}

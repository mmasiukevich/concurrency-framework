<?php

/**
 * PHP Service Bus (CQS implementation)
 *
 * @author  Maksim Masiukevich <desperado@minsk-info.ru>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace Desperado\ServiceBus\Tests\Application\Kernel;

use Desperado\ServiceBus\Annotations;
use Desperado\ServiceBus\ServiceInterface;
use Desperado\ServiceBus\Tests\Services\Stabs\TestServiceEvent;
use Desperado\ServiceBus\Tests\TestApplicationContext;
use React\Promise\FulfilledPromise;
use React\Promise\PromiseInterface;

/**
 * @Annotations\Services\Service()
 */
class TestMultipleTasksService implements ServiceInterface
{
    /**
     * @Annotations\Services\EventHandler()
     *
     * @param TestServiceEvent       $event
     * @param TestApplicationContext $context
     *
     * @return void
     */
    public function someErrorEventHandler(
        TestServiceEvent $event,
        TestApplicationContext $context
    ): void
    {
        throw new \LogicException('test fail');
    }

    /**
     * @Annotations\Services\EventHandler()
     *
     * @param TestServiceEvent       $event
     * @param TestApplicationContext $context
     *
     * @return PromiseInterface
     */
    public function someSuccessEventHandler(
        TestServiceEvent $event,
        TestApplicationContext $context
    ): PromiseInterface
    {
        return new FulfilledPromise();
    }
}

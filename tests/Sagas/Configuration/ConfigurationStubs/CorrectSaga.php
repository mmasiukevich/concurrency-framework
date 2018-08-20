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

namespace Desperado\ServiceBus\Tests\Sagas\Configuration\ConfigurationStubs;

use Desperado\ServiceBus\Common\Contract\Messages\Command;
use Desperado\ServiceBus\Sagas\Annotations\SagaEventListener;
use Desperado\ServiceBus\Sagas\Annotations\SagaHeader;
use Desperado\ServiceBus\Sagas\Saga;

/**
 * @SagaHeader(
 *     idClass="Desperado\ServiceBus\Tests\Sagas\Configuration\ConfigurationStubs\TestSagaId",
 *     containingIdProperty="requestId",
 *     expireDateModifier="+1 year"
 * )
 */
final class CorrectSaga extends Saga
{
    /**
     * @inheritdoc
     */
    public function start(Command $command): void
    {

    }

    /**
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @SagaEventListener()
     *
     * @param SomeSagaEvent $event
     *
     * @return void
     */
    private function onSomeSagaEvent(SomeSagaEvent $event): void
    {

    }

    /**
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @SagaEventListener(
     *     containingIdProperty="anotherId"
     * )
     *
     * @param SomeAnotherSagaEvent $event
     *
     * @return void
     */
    private function onSomeAnotherSagaEvent(SomeAnotherSagaEvent $event): void
    {

    }
}

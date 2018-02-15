<?php

/**
 * PHP Service Bus (CQS implementation)
 *
 * @author  Maksim Masiukevich <desperado@minsk-info.ru>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace Desperado\ServiceBus\Tests\Saga\Processor\Positive;

use Desperado\Domain\Message\AbstractEvent;

/**
 *
 */
class TestEventProcessorSagaEvent extends AbstractEvent
{
    /**
     * @var string
     */
    protected $identifierField;

    /**
     *
     *
     * @return string
     */
    public function getIdentifierField(): string
    {
        return (string) $this->identifierField;
    }
}

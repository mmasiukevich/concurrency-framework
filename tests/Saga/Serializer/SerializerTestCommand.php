<?php

/**
 * PHP Service Bus (CQS implementation)
 *
 * @author  Maksim Masiukevich <desperado@minsk-info.ru>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace Desperado\ServiceBus\Tests\Saga\Serializer;

use Desperado\Domain\Message\AbstractCommand;

/**
 *
 */
class SerializerTestCommand extends AbstractCommand
{
    /**
     *
     *
     * @var string
     */
    protected $testProperty;

    /**
     *
     *
     * @return string
     */
    public function getTestProperty(): string
    {
        return $this->testProperty;
    }
}

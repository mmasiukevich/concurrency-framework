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

namespace Desperado\ServiceBus\Sagas\Exceptions;

use Desperado\ServiceBus\Common\Exceptions\ServiceBusExceptionMarker;

/**
 *
 */
final class SagaMetaDataNotFound extends \RuntimeException implements ServiceBusExceptionMarker
{
    /**
     * @param string $sagaClass
     */
    public function __construct(string $sagaClass)
    {
        parent::__construct(
            \sprintf(
                'Meta data of the saga "%s" not found. The saga was not configured',
                $sagaClass
            )
        );
    }
}

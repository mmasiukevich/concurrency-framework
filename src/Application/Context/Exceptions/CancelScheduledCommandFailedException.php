<?php

/**
 * PHP Service Bus (CQS implementation)
 *
 * @author  Maksim Masiukevich <desperado@minsk-info.ru>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace Desperado\ServiceBus\Application\Context\Exceptions;

use Desperado\ServiceBus\ServiceBusExceptionInterface;

/**
 *
 */
class CancelScheduledCommandFailedException extends \RuntimeException implements ServiceBusExceptionInterface
{

}

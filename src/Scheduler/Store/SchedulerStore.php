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

namespace Desperado\ServiceBus\Scheduler\Store;

use Desperado\ServiceBus\Scheduler\Data\ScheduledOperation;
use Desperado\ServiceBus\Scheduler\ScheduledOperationId;

/**
 *
 */
interface SchedulerStore
{
    /**
     * Extract operation (load and delete)
     *
     * @param ScheduledOperationId $id
     * @param callable             $postExtract function(ScheduledOperation $operation) {}
     *
     * @return \Generator It does not return any result
     *
     * @throws \Desperado\ServiceBus\Scheduler\Exceptions\ScheduledOperationNotFound
     * @throws \Desperado\ServiceBus\Infrastructure\Storage\Exceptions\ConnectionFailed
     * @throws \Desperado\ServiceBus\Infrastructure\Storage\Exceptions\StorageInteractingFailed
     */
    public function extract(ScheduledOperationId $id, callable $postExtract): \Generator;

    /**
     * Remove operation
     *
     * @param ScheduledOperationId $id
     * @param callable             $postRemove function(?NextScheduledOperation){}
     *
     * @return \Generator<bool>
     *
     * @throws \Desperado\ServiceBus\Infrastructure\Storage\Exceptions\ConnectionFailed
     * @throws \Desperado\ServiceBus\Infrastructure\Storage\Exceptions\StorageInteractingFailed
     */
    public function remove(ScheduledOperationId $id, callable $postRemove): \Generator;

    /**
     * Save new operation
     *
     * @param ScheduledOperation $operation
     * @param callable           $postAdd function(ScheduledOperation $operation, ?NextScheduledOperation) {}
     *
     * @return \Generator It does not return any result
     *
     * @throws \Desperado\ServiceBus\Infrastructure\Storage\Exceptions\UniqueConstraintViolationCheckFailed
     * @throws \Desperado\ServiceBus\Infrastructure\Storage\Exceptions\ConnectionFailed
     * @throws \Desperado\ServiceBus\Infrastructure\Storage\Exceptions\StorageInteractingFailed
     */
    public function add(ScheduledOperation $operation, callable $postAdd): \Generator;
}

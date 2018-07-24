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

namespace Desperado\ServiceBus\EventSourcing\EventStreamStore\Sql;

use function Amp\call;
use Amp\Promise;
use Amp\Success;
use Desperado\ServiceBus\EventSourcing\Aggregate;
use Desperado\ServiceBus\EventSourcing\AggregateId;
use Desperado\ServiceBus\EventSourcing\EventStreamStore\AggregateStore;
use Desperado\ServiceBus\EventSourcing\EventStreamStore\StoredAggregateEvent;
use Desperado\ServiceBus\EventSourcing\EventStreamStore\StoredAggregateEventStream;
use function Desperado\ServiceBus\Storage\fetchAll;
use function Desperado\ServiceBus\Storage\fetchOne;
use Desperado\ServiceBus\Storage\StorageAdapter;
use Desperado\ServiceBus\Storage\TransactionAdapter;

/**
 * Events storage backend (SQL-based)
 */
final class SqlEventStreamStore implements AggregateStore
{
    /**
     * @var StorageAdapter
     */
    private $adapter;

    /**
     * @param StorageAdapter $adapter
     */
    public function __construct(StorageAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * @inheritdoc
     */
    public function saveStream(StoredAggregateEventStream $aggregateEventStream, callable $afterSaveHandler): Promise
    {
        $adapter = $this->adapter;

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(StoredAggregateEventStream $eventsStream) use ($adapter, $afterSaveHandler): \Generator
            {
                /** @var \Desperado\ServiceBus\Storage\TransactionAdapter $transaction */
                $transaction = yield $adapter->transaction();

                try
                {
                    yield self::doSaveStream($transaction, $eventsStream);
                    yield self::doSaveEvents($transaction, $eventsStream);

                    yield call($afterSaveHandler);

                    yield $transaction->commit();
                }
                catch(\Throwable $throwable)
                {
                    yield $transaction->rollback();

                    throw $throwable;
                }
            },
            $aggregateEventStream
        );
    }

    /**
     * @inheritdoc
     */
    public function appendStream(StoredAggregateEventStream $aggregateEventStream, callable $afterSaveHandler): promise
    {
        $adapter = $this->adapter;

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(StoredAggregateEventStream $eventsStream) use ($adapter, $afterSaveHandler): \Generator
            {
                /** @var \Desperado\ServiceBus\Storage\TransactionAdapter $transaction */
                $transaction = yield $adapter->transaction();

                try
                {
                    yield self::doSaveEvents($transaction, $eventsStream);

                    yield call($afterSaveHandler);

                    yield $transaction->commit();

                    return yield new Success();
                }
                catch(\Throwable $throwable)
                {
                    yield $transaction->rollback();

                    throw $throwable;
                }
            },
            $aggregateEventStream
        );
    }

    /**
     * @inheritdoc
     */
    public function loadStream(
        AggregateId $id,
        int $fromVersion = Aggregate::START_PLAYHEAD_INDEX,
        ?int $toVersion = null
    ): Promise
    {
        $adapter = $this->adapter;

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(AggregateId $id, int $fromVersion, ?int $toVersion) use ($adapter): \Generator
            {
                $aggregateEventStream = null;

                /** @var array|null $streamData */
                $streamData = yield self::doLoadStream($adapter, $id);

                if(null !== $streamData)
                {
                    $streamEventsData = yield self::doLoadStreamEvents(
                        $adapter,
                        $streamData['id'],
                        $fromVersion,
                        $toVersion
                    );

                    $aggregateEventStream = self::restoreEventStream($adapter, $streamData, $streamEventsData);
                }

                return yield new Success($aggregateEventStream);
            },
            $id, $fromVersion, $toVersion
        );
    }

    /**
     * @inheritdoc
     */
    public function closeStream(AggregateId $id): Promise
    {
        $adapter = $this->adapter;

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(AggregateId $id) use ($adapter): \Generator
            {
                yield $adapter->execute(
                    'UPDATE event_store_stream SET closed_at = ? WHERE id = ? AND identifier_class = ?', [
                        \date('Y-m-d H:i:s'),
                        (string) $id,
                        \get_class($id)
                    ]
                );

                return yield new Success();
            },
            $id
        );
    }

    /**
     * Store the parent event stream
     *
     * @param TransactionAdapter         $transaction
     * @param StoredAggregateEventStream $eventsStream
     *
     * @psalm-suppress MoreSpecificReturnType Incorrect resolving the value of the promise
     * @psalm-suppress LessSpecificReturnStatement Incorrect resolving the value of the promise
     *
     * @return Promise<null>
     *
     * @throws \Desperado\ServiceBus\Storage\Exceptions\ConnectionFailed
     * @throws \Desperado\ServiceBus\Storage\Exceptions\OperationFailed
     * @throws \Desperado\ServiceBus\Storage\Exceptions\UniqueConstraintViolationCheckFailed
     * @throws \Desperado\ServiceBus\Storage\Exceptions\StorageInteractingFailed
     */
    private static function doSaveStream(TransactionAdapter $transaction, StoredAggregateEventStream $eventsStream): Promise
    {
        $sql = /** @lang text */
            'INSERT INTO event_store_stream (id, identifier_class, aggregate_class, created_at, closed_at) VALUES (?, ?, ?, ?, ?)';

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(TransactionAdapter $transaction, StoredAggregateEventStream $eventsStream) use ($sql): \Generator
            {
                yield $transaction->execute(
                    $sql, [
                        $eventsStream->aggregateId(),
                        $eventsStream->getAggregateIdClass(),
                        $eventsStream->aggregateClass(),
                        $eventsStream->createdAt(),
                        $eventsStream->closedAt()
                    ]
                );

                return yield new Success();
            },
            $transaction,
            $eventsStream
        );
    }

    /**
     * Saving events in stream
     *
     * @param TransactionAdapter         $transaction
     * @param StoredAggregateEventStream $eventsStream
     *
     * @return Promise<null>
     *
     * @psalm-suppress MoreSpecificReturnType Incorrect resolving the value of the promise
     * @psalm-suppress LessSpecificReturnStatement Incorrect resolving the value of the promise
     *
     * @throws \Desperado\ServiceBus\Storage\Exceptions\ConnectionFailed
     * @throws \Desperado\ServiceBus\Storage\Exceptions\OperationFailed
     * @throws \Desperado\ServiceBus\Storage\Exceptions\UniqueConstraintViolationCheckFailed
     * @throws \Desperado\ServiceBus\Storage\Exceptions\StorageInteractingFailed
     */
    private static function doSaveEvents(TransactionAdapter $transaction, StoredAggregateEventStream $eventsStream): Promise
    {
        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(TransactionAdapter $transaction, StoredAggregateEventStream $eventsStream): \Generator
            {
                $eventsCount = \count($eventsStream->aggregateEvents());

                if(0 !== $eventsCount)
                {
                    yield $transaction->execute(
                        self::createSaveEventQueryString($eventsCount),
                        self::collectSaveEventQueryParameters($eventsStream)
                    );
                }

                return yield new Success();
            },
            $transaction,
            $eventsStream
        );
    }

    /**
     * Create a sql query to store events
     *
     * @param int $eventsCount
     *
     * @return string
     */
    private static function createSaveEventQueryString(int $eventsCount): string
    {
        return \sprintf(
        /** @lang text */
            'INSERT INTO event_store_stream_events (id, stream_id, playhead, event_class, payload, occured_at, recorded_at) VALUES %s',
            \implode(
                ', ', \array_fill(0, $eventsCount, '(?, ?, ?, ?, ?, ?, ?)')
            )
        );
    }

    /**
     * Gathering parameters for sending to a request to save events
     *
     * @param StoredAggregateEventStream $eventsStream
     *
     * @return array
     */
    private static function collectSaveEventQueryParameters(StoredAggregateEventStream $eventsStream): array
    {
        $queryParameters = [];
        $rowSetIndex     = 0;

        foreach(self::prepareEventRows($eventsStream) as $parameters)
        {
            /** @var array $parameters */

            foreach($parameters as $parameter)
            {
                $queryParameters[$rowSetIndex] = $parameter;

                $rowSetIndex++;
            }
        }

        return $queryParameters;
    }

    /**
     * Prepare events to insert
     *
     * @param StoredAggregateEventStream $eventsStream
     *
     * @return array
     */
    private static function prepareEventRows(StoredAggregateEventStream $eventsStream): array
    {
        $eventsRows = [];

        foreach($eventsStream->aggregateEvents() as $storedAggregateEvent)
        {
            /** @var StoredAggregateEvent $storedAggregateEvent */

            $row = [
                $storedAggregateEvent->eventId(),
                $eventsStream->aggregateId(),
                $storedAggregateEvent->playheadPosition(),
                $storedAggregateEvent->eventClass(),
                $storedAggregateEvent->eventData(),
                $storedAggregateEvent->occuredAt(),
                \date('Y-m-d H:i:s')
            ];

            $eventsRows[] = $row;
        }

        return $eventsRows;
    }

    /**
     * Execute load event stream
     *
     * @param StorageAdapter $adapter
     * @param AggregateId    $id
     *
     * @psalm-suppress MoreSpecificReturnType Incorrect resolving the value of the promise
     * @psalm-suppress LessSpecificReturnStatement Incorrect resolving the value of the promise
     *
     * @return Promise<array<mixed, mixed>>
     *
     * @throws \Desperado\ServiceBus\Storage\Exceptions\ConnectionFailed
     * @throws \Desperado\ServiceBus\Storage\Exceptions\OperationFailed
     * @throws \Desperado\ServiceBus\Storage\Exceptions\StorageInteractingFailed
     */
    private static function doLoadStream(StorageAdapter $adapter, AggregateId $id): Promise
    {
        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(StorageAdapter $adapter, AggregateId $id): \Generator
            {
                $data = yield fetchOne(
                    yield $adapter->execute(
                        'SELECT * FROM event_store_stream WHERE id = ? AND identifier_class = ?',
                        [(string) $id, \get_class($id)]
                    )
                );

                return yield new Success($data);
            },
            $adapter,
            $id
        );
    }

    /**
     * Load events for specified stream
     *
     * @param StorageAdapter $adapter
     * @param string         $streamId
     * @param int            $fromVersion
     * @param int|null       $toVersion
     *
     * @psalm-suppress MoreSpecificReturnType Incorrect resolving the value of the promise
     * @psalm-suppress LessSpecificReturnStatement Incorrect resolving the value of the promise
     *
     * @return Promise<array<mixed, array>>
     *
     * @throws \Desperado\ServiceBus\Storage\Exceptions\ConnectionFailed
     * @throws \Desperado\ServiceBus\Storage\Exceptions\OperationFailed
     * @throws \Desperado\ServiceBus\Storage\Exceptions\StorageInteractingFailed
     */
    private static function doLoadStreamEvents(
        StorageAdapter $adapter,
        string $streamId,
        int $fromVersion,
        ?int $toVersion
    ): Promise
    {
        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(StorageAdapter $adapter, string $streamId, int $fromVersion, ?int $toVersion): \Generator
            {
                $statements = [$streamId, $fromVersion];
                $sql        = 'SELECT * FROM event_store_stream_events WHERE stream_id = ? AND playhead >= ?';

                if(null !== $toVersion && $fromVersion < $toVersion)
                {
                    $sql .= ' AND playhead <= ?';

                    $statements[] = $toVersion;
                }

                return yield new Success(
                    yield fetchAll(
                        yield $adapter->execute($sql, $statements)
                    )
                );
            },
            $adapter,
            $streamId,
            $fromVersion,
            $toVersion
        );
    }

    /**
     * Transform events stream array data to stored representation
     *
     * @param StorageAdapter $adapter
     * @param array          $streamData
     * @param array|null     $streamEventsData
     *
     * @return StoredAggregateEventStream
     */
    private static function restoreEventStream(
        StorageAdapter $adapter,
        array $streamData,
        ?array $streamEventsData
    ): StoredAggregateEventStream
    {
        return new StoredAggregateEventStream(
            $streamData['id'],
            $streamData['identifier_class'],
            $streamData['aggregate_class'],
            self::restoreEvents($adapter, $streamEventsData),
            $streamData['created_at'],
            $streamData['closed_at']
        );
    }

    /**
     * Restore events from rows
     *
     * @param StorageAdapter $adapter
     * @param array|null     $eventsData
     *
     * @return array<int, \Desperado\ServiceBus\EventSourcing\EventStreamStore\StoredAggregateEvent>
     */
    private static function restoreEvents(StorageAdapter $adapter, ?array $eventsData): array
    {
        $events = [];

        if(true === \is_array($eventsData) && 0 !== \count($eventsData))
        {
            foreach($eventsData as $eventRow)
            {
                $playhead = (int) $eventRow['playhead'];

                $events[$playhead] = new StoredAggregateEvent(
                    $eventRow['id'],
                    $playhead,
                    $adapter->unescapeBinary($eventRow['payload']),
                    $eventRow['event_class'],
                    $eventRow['occured_at'],
                    $eventRow['recorded_at']
                );
            }
        }

        return $events;
    }
}

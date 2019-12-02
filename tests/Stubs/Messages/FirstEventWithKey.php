<?php

/**
 * PHP Service Bus (publish-subscribe pattern implementation).
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Tests\Stubs\Messages;

/**
 *
 */
final class FirstEventWithKey
{
    private string $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function key(): string
    {
        return $this->key;
    }
}

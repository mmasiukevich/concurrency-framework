<?php

/**
 * Command Query Responsibility Segregation, Event Sourcing implementation
 *
 * @author  Maksim Masiukevich <desperado@minsk-info.ru>
 * @url     https://github.com/mmasiukevich
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace Desperado\Framework\Common\Utils;

/**
 * Object helpers
 */
class ObjectUtils
{
    /**
     * Get class name for specified object
     *
     * @param object|string $object
     *
     * @return string
     */
    public static function getClassName($object): string
    {
        $objectNamespace = true === \is_object($object) ? \get_class($object) : $object;

        return \implode('', \array_slice(\explode('\\', $objectNamespace), -1));
    }

    /**
     * Recursive get_object_vars
     *
     * @param object $object
     *
     * @return array
     */
    public static function getObjectVars($object): array
    {
        $result = [];
        $asArray = \get_object_vars($object);

        foreach($asArray as $key => $item)
        {
            if(true === \is_object($item))
            {
                if(\spl_object_hash($object) !== \spl_object_hash($item))
                {
                    $result[$key] = self::getObjectVars($item);
                }
            }
            else
            {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * Close constructor
     *
     * @codeCoverageIgnore
     */
    private function __construct()
    {

    }
}

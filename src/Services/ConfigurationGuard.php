<?php

/**
 * PHP Service Bus (CQS implementation)
 *
 * @author  Maksim Masiukevich <desperado@minsk-info.ru>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace Desperado\ServiceBus\Services\Configuration;

use Desperado\Domain\Message\AbstractMessage;
use Desperado\ServiceBus\Annotations\ErrorHandler;
use Desperado\ServiceBus\MessageProcessor\AbstractExecutionContext;
use Desperado\ServiceBus\Services\Exceptions as ServicesExceptions;
use Desperado\ServiceBus\Services\Handlers\Exceptions\UnfulfilledPromiseData;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

/**
 * The helper for validating the handlers
 */
class ConfigurationGuard
{
    /**
     * Assert handlers return declaration type is correct
     *
     * @param \ReflectionMethod $method
     *
     * @return void
     *
     * @throws ServicesExceptions\NoReturnTypeDeclarationException
     * @throws ServicesExceptions\IncorrectReturnTypeDeclarationException
     */
    public static function guardHandlerReturnDeclaration(\ReflectionMethod $method): void
    {
        if(false === $method->hasReturnType())
        {
            throw new ServicesExceptions\NoReturnTypeDeclarationException($method);
        }

        $returnDeclarationType = $method->getReturnType()->getName();

        if(PromiseInterface::class !== $returnDeclarationType && Promise::class !== $returnDeclarationType)
        {
            throw new ServicesExceptions\IncorrectReturnTypeDeclarationException($method);
        }
    }


    /**
     * Assert arguments count valid
     *
     * @param \ReflectionMethod $reflectionMethod
     * @param int               $expectedParametersCount
     *
     * @return void
     *
     * @throws ServicesExceptions\InvalidHandlerArgumentsCountException
     */
    public static function guardNumberOfParametersValid(
        \ReflectionMethod $reflectionMethod,
        int $expectedParametersCount
    ): void
    {
        if($expectedParametersCount !== $reflectionMethod->getNumberOfRequiredParameters())
        {
            throw new ServicesExceptions\InvalidHandlerArgumentsCountException(
                $reflectionMethod,
                $expectedParametersCount
            );
        }
    }

    /**
     * Assert context argument is valid
     *
     * @param \ReflectionMethod    $reflectionMethod
     * @param \ReflectionParameter $parameter
     *
     * @return void
     *
     * @throws ServicesExceptions\InvalidHandlerArgumentException
     */
    public static function guardContextValidArgument(
        \ReflectionMethod $reflectionMethod,
        \ReflectionParameter $parameter
    )
    {
        if(
            null === $parameter->getClass() ||
            false === $parameter->getClass()->isSubclassOf(AbstractExecutionContext::class)
        )
        {
            throw new ServicesExceptions\InvalidHandlerArgumentException(
                \sprintf(
                    'The second argument to the handler "%s:%s" must be instanceof the "%s"',
                    $reflectionMethod->getDeclaringClass()->getName(),
                    $reflectionMethod->getName(),
                    AbstractExecutionContext::class
                )
            );
        }
    }

    /**
     * Assert message type is correct
     *
     * @param \ReflectionMethod    $reflectionMethod
     * @param \ReflectionParameter $parameter
     * @param int                  $argumentPosition
     *
     * @return void
     *
     * @throws ServicesExceptions\InvalidHandlerArgumentException
     */
    public static function guardValidMessageArgument(
        \ReflectionMethod $reflectionMethod,
        \ReflectionParameter $parameter,
        int $argumentPosition
    ): void
    {
        if(
            null === $parameter->getClass() ||
            false === $parameter->getClass()->isSubclassOf(AbstractMessage::class)
        )
        {
            throw new ServicesExceptions\InvalidHandlerArgumentException(
                \sprintf(
                    'The %d argument to the handler "%s:%s" must be instanceof the "%s" (%s specified)',
                    $argumentPosition,
                    $reflectionMethod->getDeclaringClass()->getName(),
                    $reflectionMethod->getName(),
                    AbstractMessage::class,
                    null !== $parameter->getClass()
                        ? $parameter->getClass()->getName()
                        : 'n/a'
                )
            );
        }
    }

    /**
     * Checking the correctness of the arguments of the error handler
     *
     * @param \ReflectionMethod    $reflectionMethod
     * @param \ReflectionParameter $parameter
     *
     * @return void
     *
     * @throws ServicesExceptions\InvalidHandlerArgumentException
     */
    public static function guardUnfulfilledPromiseArgument(
        \ReflectionMethod $reflectionMethod,
        \ReflectionParameter $parameter
    ): void
    {
        if(
            null === $parameter->getClass() ||
            UnfulfilledPromiseData::class !== $parameter->getClass()->getName()
        )
        {
            throw new ServicesExceptions\InvalidHandlerArgumentException(
                \sprintf(
                    'The first argument to the handler "%s:%s" must be instanceof the "%s"',
                    $reflectionMethod->getDeclaringClass()->getName(),
                    $reflectionMethod->getName(),
                    UnfulfilledPromiseData::class
                )
            );
        }
    }

    /**
     * Checking the correctness of the annotation parameters of the error handler
     *
     * @param ErrorHandler      $errorHandlerAnnotation
     * @param \ReflectionMethod $reflectionMethod
     *
     * @return void
     *
     * @throws ServicesExceptions\IncorrectAnnotationDataException
     */
    public static function guardErrorHandlerAnnotationData(
        ErrorHandler $errorHandlerAnnotation,
        \ReflectionMethod $reflectionMethod
    ): void
    {
        if('' === (string) $errorHandlerAnnotation->getMessageClass())
        {
            throw new ServicesExceptions\IncorrectAnnotationDataException(
                \sprintf(
                    'Message for which an exception will be caught not specified (%s:%s). Configure "%s::$message" parameter',
                    $reflectionMethod->getDeclaringClass()->getName(),
                    $reflectionMethod->getName(),
                    ErrorHandler::class
                )
            );
        }

        if(false === \class_exists($errorHandlerAnnotation->getMessageClass()))
        {
            throw new ServicesExceptions\IncorrectAnnotationDataException(
                \sprintf(
                    'The class of the message ("%s") for which the exceptions will not be caught is not found. (%s:%s). '
                    . 'Configure "%s::$message" parameter',
                    $errorHandlerAnnotation->getMessageClass(),
                    $reflectionMethod->getDeclaringClass()->getName(),
                    $reflectionMethod->getName(),
                    ErrorHandler::class
                )
            );
        }

        if(
            false === \class_exists($errorHandlerAnnotation->getThrowableType()) &&
            false === \interface_exists($errorHandlerAnnotation->getThrowableType())
        )
        {
            throw new ServicesExceptions\IncorrectAnnotationDataException(
                \sprintf(
                    'An incorrect type of intercepting exceptions is indicated. "%s" not found. (%s:%s). '
                    . 'Configure "%s::$type" parameter',
                    $errorHandlerAnnotation->getThrowableType(),
                    $reflectionMethod->getDeclaringClass()->getName(),
                    $reflectionMethod->getName(),
                    ErrorHandler::class
                )
            );
        }
    }

    /**
     * Close constructor
     */
    private function __construct()
    {

    }
}

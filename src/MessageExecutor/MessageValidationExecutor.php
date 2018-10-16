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

namespace Desperado\ServiceBus\MessageExecutor;

use Amp\Failure;
use Amp\Promise;
use function Desperado\ServiceBus\Common\invokeReflectionMethod;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ValidatorBuilder;
use Desperado\ServiceBus\Application\KernelContext;
use Desperado\ServiceBus\Common\Contract\Messages\Message;

/**
 * Executing message validation
 */
final class MessageValidationExecutor implements MessageExecutor
{
    /**
     * @var MessageExecutor
     */
    private $executor;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var array<mixed, string>
     */
    private $validationGroups;

    /**
     * @param MessageExecutor $processor
     * @param array           $validationGroups
     */
    public function __construct(MessageExecutor $executor, array $validationGroups)
    {
        $this->executor         = $executor;
        $this->validationGroups = $validationGroups;

        $this->validator = (new ValidatorBuilder())
            ->enableAnnotationMapping()
            ->getValidator();
    }

    /**
     * @inheritdoc
     */
    public function __invoke(Message $message, KernelContext $context): Promise
    {
        try
        {
            /** @var ConstraintViolationList $violations */
            $violations = $this->validator->validate($message, null, $this->validationGroups);
        }
        catch(\Throwable $throwable)
        {
            return new Failure($throwable);
        }

        if(0 !== \count($violations))
        {
            self::bindViolations($violations, $context);
        }

        return ($this->executor)($message, $context);
    }

    /**
     * Bind violations to context
     *
     * @param ConstraintViolationList $violations
     * @param KernelContext           $context
     *
     * @return void
     */
    private static function bindViolations(ConstraintViolationList $violations, KernelContext $context): void
    {
        $errors = [];

        foreach($violations as $violation)
        {
            /** @var \Symfony\Component\Validator\ConstraintViolation $violation */

            $errors[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        try
        {
            invokeReflectionMethod($context, 'validationFailed', $errors);
        }
        catch(\Throwable $throwable)
        {
            /** No exceptions can happen */
        }
    }
}

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

namespace Desperado\ServiceBus\Tests\Sagas\Configuration;

use Desperado\ServiceBus\SagaProvider;
use Desperado\ServiceBus\Sagas\Configuration\AnnotationsBasedSagaConfigurationLoader;
use Desperado\ServiceBus\Tests\Sagas\Configuration\ConfigurationStubs\CorrectSaga;
use Desperado\ServiceBus\Tests\Sagas\Configuration\ConfigurationStubs\SagaWithIncorrectEventListenerClass;
use Desperado\ServiceBus\Tests\Sagas\Configuration\ConfigurationStubs\SagaWithoutAnnotations;
use Desperado\ServiceBus\Tests\Sagas\Configuration\ConfigurationStubs\SagaWithoutListeners;
use Desperado\ServiceBus\Tests\Sagas\Configuration\ConfigurationStubs\SagaWithToManyArguments;
use Desperado\ServiceBus\Tests\Sagas\Configuration\ConfigurationStubs\SagaWithUnExistsEventListenerClass;
use Desperado\ServiceBus\Tests\Sagas\Configuration\ConfigurationStubs\SagaWrongIdClassSpecified;
use Desperado\ServiceBus\Tests\Sagas\Configuration\ConfigurationStubs\TestSagaStoreImplementation;
use PHPUnit\Framework\TestCase;

/**
 *
 */
final class AnnotationsBasedSagaListenersLoaderTest extends TestCase
{
    /**
     * @var SagaProvider
     */
    private $sagaProvider;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->sagaProvider = new SagaProvider(new TestSagaStoreImplementation);
    }

    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->sagaProvider);
    }


    /**
     * @test
     * @expectedException \Desperado\ServiceBus\Sagas\Configuration\Exceptions\InvalidSagaConfiguration
     * @expectedExceptionMessage Could not find class-level annotation
     *                           "Desperado\ServiceBus\Sagas\Annotations\SagaHeader" in
     *                           "Desperado\ServiceBus\Tests\Sagas\Configuration\Stubs\SagaWithoutAnnotations"
     *
     * @return void
     */
    public function sagaWithoutAnnotations(): void
    {
        (new AnnotationsBasedSagaConfigurationLoader($this->sagaProvider))->load(SagaWithoutAnnotations::class);
    }

    /**
     * @test
     * @expectedException \Desperado\ServiceBus\Sagas\Configuration\Exceptions\InvalidSagaConfiguration
     * @expectedExceptionMessage In the meta data of the saga
     *                           "Desperado\ServiceBus\Tests\Sagas\Configuration\Stubs\SagaWrongIdClassSpecified", an
     *                           incorrect value of the "idClass"
     *
     * @return void
     */
    public function sagaWithIncorrectHeaderAnnotationData(): void
    {
        (new AnnotationsBasedSagaConfigurationLoader($this->sagaProvider))
            ->load(SagaWrongIdClassSpecified::class)
            ->handlerCollection();
    }

    /**
     * @test
     *
     * @return void
     */
    public function sagaWithoutListeners(): void
    {
        $result = (new AnnotationsBasedSagaConfigurationLoader($this->sagaProvider))
            ->load(SagaWithoutListeners::class)
            ->handlerCollection();

        static::assertEmpty($result);
    }

    /**
     * @test
     *
     * @return void
     */
    public function correctSagaWithListeners(): void
    {
        $result = (new AnnotationsBasedSagaConfigurationLoader($this->sagaProvider))
            ->load(CorrectSaga::class)
            ->handlerCollection();

        static::assertNotEmpty($result);
        static::assertCount(2, $result);

        foreach($result as $messageHandler)
        {
            /** @var \Desperado\ServiceBus\MessageBus\MessageHandler\Handler $messageHandler */
            static::assertFalse($messageHandler->isCommandHandler());
            static::assertTrue($messageHandler->hasReturnDeclaration());

            $closure = $messageHandler->toClosure();

            /** @noinspection UnnecessaryAssertionInspection */
            static::assertInstanceOf(\Closure::class, $closure);
            /** @see SagaEventListenerProcessor */
            static::assertCount(2, $messageHandler->arguments());
        }
    }

    /**
     * @test
     * @expectedException \Desperado\ServiceBus\Sagas\Configuration\Exceptions\InvalidSagaConfiguration
     *
     * @return void
     */
    public function sagaWithUnExistsEventClass(): void
    {
        (new AnnotationsBasedSagaConfigurationLoader($this->sagaProvider))->load(SagaWithUnExistsEventListenerClass::class);
    }

    /**
     * @test
     * @expectedException \Desperado\ServiceBus\Sagas\Configuration\Exceptions\InvalidSagaConfiguration
     *
     * @return void
     */
    public function sagaWithToManyListenerArguments(): void
    {
        (new AnnotationsBasedSagaConfigurationLoader($this->sagaProvider))->load(SagaWithToManyArguments::class);
    }

    /**
     * @test
     * @expectedException \Desperado\ServiceBus\Sagas\Configuration\Exceptions\InvalidSagaConfiguration
     *
     * @return void
     */
    public function sagaWithIncorrectListenerClass(): void
    {
        (new AnnotationsBasedSagaConfigurationLoader($this->sagaProvider))->load(SagaWithIncorrectEventListenerClass::class);
    }
}

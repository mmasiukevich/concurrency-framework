<?php /** @noinspection PhpUnhandledExceptionInspection */

/**
 * PHP Service Bus (publish-subscribe pattern implementation).
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Tests\EntryPoint;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ServiceBus\EntryPoint\DefaultEntryPointProcessor;
use ServiceBus\EntryPoint\IncomingMessageDecoder;
use ServiceBus\MessageExecutor\DefaultMessageExecutor;
use ServiceBus\MessageSerializer\Symfony\SymfonySerializer;
use ServiceBus\MessagesRouter\Router;
use ServiceBus\Metadata\ServiceBusMetadata;
use ServiceBus\Services\Configuration\DefaultHandlerOptions;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use function Amp\Promise\wait;
use function ServiceBus\Common\jsonEncode;
use function ServiceBus\Common\uuid;
use function ServiceBus\Tests\filterLogMessages;

/**
 *
 */
final class DefaultEntryPointProcessorTest extends TestCase
{
    /**
     * @var TestHandler
     */
    private $logHandler;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EntryPointTestContextFactory
     */
    private $contextFactory;

    /**
     * @var IncomingMessageDecoder
     */
    private $messageDecoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logHandler = new TestHandler();
        $this->logger     = new Logger('tests', [$this->logHandler]);

        $this->contextFactory = new EntryPointTestContextFactory($this->logger);

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->set('default_serializer', new SymfonySerializer());

        $this->messageDecoder = new IncomingMessageDecoder(
            ['service_bus.decoder.default_handler' => 'default_serializer'],
            $containerBuilder
        );
    }

    /**
     * @test
     */
    public function decodeFailed(): void
    {
        $processor = new DefaultEntryPointProcessor(
            $this->messageDecoder,
            $this->contextFactory,
            null,
            $this->logger
        );

        $package = new EntryPointTestIncomingPackage(
            payload: 'qwerty',
            headers: [ServiceBusMetadata::SERVICE_BUS_MESSAGE_TYPE => EntryPointTestMessage::class],
            messageId: uuid()
        );

        wait($processor->handle($package));

        self::assertContains('Failed to denormalize the message', filterLogMessages($this->logHandler));
    }

    /**
     * @test
     */
    public function withoutHandlers(): void
    {
        $processor = new DefaultEntryPointProcessor(
            $this->messageDecoder,
            $this->contextFactory,
            null,
            $this->logger
        );

        $payload = self::serialize(new EntryPointTestMessage('id'));
        $package = new EntryPointTestIncomingPackage(
            payload: $payload,
            headers: [ServiceBusMetadata::SERVICE_BUS_MESSAGE_TYPE => EntryPointTestMessage::class],
            messageId: uuid()
        );

        wait($processor->handle($package));

        self::assertContains(
            'There are no handlers configured for the message "{messageClass}"',
            filterLogMessages($this->logHandler)
        );
    }

    /**
     * @test
     */
    public function withFailedHandler(): void
    {
        $router = new Router();

        $closure = \Closure::fromCallable(
            static function (): void
            {
                throw new \RuntimeException('Some message execution failed');
            }
        );

        $executor = new DefaultMessageExecutor(
            handlerHash: '',
            closure: $closure,
            arguments: new \SplObjectStorage(),
            options: DefaultHandlerOptions::createForCommandHandler(),
            argumentResolvers: []
        );

        $router->registerHandler(EntryPointTestMessage::class, $executor);

        $processor = new DefaultEntryPointProcessor(
            $this->messageDecoder,
            $this->contextFactory,
            $router,
            $this->logger
        );

        $payload = self::serialize(new EntryPointTestMessage('id'));
        $package = new EntryPointTestIncomingPackage(
            payload: $payload,
            headers: [ServiceBusMetadata::SERVICE_BUS_MESSAGE_TYPE => EntryPointTestMessage::class],
            messageId: uuid()
        );

        wait($processor->handle($package));

        self::assertContains('Some message execution failed', filterLogMessages($this->logHandler));
    }

    /**
     * @test
     */
    public function successExecution(): void
    {
        $variable = 'processing';

        $router = new Router();

        $closure = \Closure::fromCallable(
            static function () use (&$variable): void
            {
                $variable = 'handled';
            }
        );

        $executor = new DefaultMessageExecutor(
            handlerHash: '',
            closure: $closure,
            arguments: new \SplObjectStorage(),
            options: DefaultHandlerOptions::createForCommandHandler(),
            argumentResolvers: []
        );

        $router->registerHandler(EntryPointTestMessage::class, $executor);

        $processor = new DefaultEntryPointProcessor(
            $this->messageDecoder,
            $this->contextFactory,
            $router,
            $this->logger
        );

        $payload = self::serialize(new EntryPointTestMessage('id'));
        $package = new EntryPointTestIncomingPackage(
            payload: $payload,
            headers: [ServiceBusMetadata::SERVICE_BUS_MESSAGE_TYPE => EntryPointTestMessage::class],
            messageId: uuid()
        );

        wait($processor->handle($package));

        self::assertSame('handled', $variable);
    }

    private static function serialize(object $message): string
    {
        return jsonEncode([
            'namespace' => \get_class($message),
            'message'   => $message->jsonSerialize()
        ]);
    }
}

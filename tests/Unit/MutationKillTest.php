<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\Async\FiberAsyncRuntime;
use CloudCastle\ConnectManager\Async\NativeStreamSelector;
use CloudCastle\ConnectManager\Connection\LogicalConnection;
use CloudCastle\ConnectManager\Connection\StreamConnection;
use CloudCastle\ConnectManager\ConnectManager;
use CloudCastle\ConnectManager\Contract\AsyncRuntimeInterface;
use CloudCastle\ConnectManager\Contract\ConnectionFactoryInterface;
use CloudCastle\ConnectManager\Contract\ConnectionInterface;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Contract\ConnectionPoolInterface;
use CloudCastle\ConnectManager\Driver\ConnectionFactory;
use CloudCastle\ConnectManager\Driver\StreamOpener;
use CloudCastle\ConnectManager\Driver\TransportResolver;
use CloudCastle\ConnectManager\Exception\ConnectionException;
use CloudCastle\ConnectManager\Exception\ConnectManagerException;
use CloudCastle\ConnectManager\Exception\ProtocolException;
use CloudCastle\ConnectManager\Exception\SecurityException;
use CloudCastle\ConnectManager\Exception\TimeoutException;
use CloudCastle\ConnectManager\Pool\ConnectionPool;
use CloudCastle\ConnectManager\Protocol\ProtocolCatalog;
use CloudCastle\ConnectManager\Protocol\ProtocolDefinition;
use CloudCastle\ConnectManager\Protocol\ProtocolRegistry;
use CloudCastle\ConnectManager\Security\EndpointGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Точечные тесты против типичных мутантов Infection.
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.ShortVariable")
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 * @SuppressWarnings("PHPMD.UnusedFormalParameter")
 * @SuppressWarnings("PHPMD.MissingImport")
 */
#[CoversClass(ConnectManager::class)]
#[CoversClass(FiberAsyncRuntime::class)]
#[CoversClass(NativeStreamSelector::class)]
#[CoversClass(ConnectionOptions::class)]
#[CoversClass(LogicalConnection::class)]
#[CoversClass(StreamConnection::class)]
#[CoversClass(ConnectionFactory::class)]
#[CoversClass(StreamOpener::class)]
#[CoversClass(TransportResolver::class)]
#[CoversClass(ConnectionPool::class)]
#[CoversClass(ProtocolCatalog::class)]
#[CoversClass(ProtocolDefinition::class)]
#[CoversClass(ProtocolRegistry::class)]
#[CoversClass(EndpointGuard::class)]
#[CoversClass(ConnectManagerException::class)]
#[CoversClass(ConnectionException::class)]
#[CoversClass(ProtocolException::class)]
#[CoversClass(SecurityException::class)]
#[CoversClass(TimeoutException::class)]
#[UsesClass(\CloudCastle\ConnectManager\Driver\Http\HttpClient::class)]
#[UsesClass(\CloudCastle\ConnectManager\Driver\Ssh\SshConnector::class)]
#[UsesClass(\CloudCastle\ConnectManager\Security\PinnedEndpoint::class)]
final class MutationKillTest extends TestCase
{
    /**
     * Дефолты сигнатур (чтение через reflection).
     */
    public function testDefaultParameterValues(): void
    {
        $chunk = new ReflectionMethod(StreamConnection::class, 'readChunks');
        self::assertSame(8192, $chunk->getParameters()[0]->getDefaultValue());

        $logicalChunk = new ReflectionMethod(LogicalConnection::class, 'readChunks');
        self::assertSame(8192, $logicalChunk->getParameters()[0]->getDefaultValue());

        $pool = new ReflectionMethod(ConnectionPool::class, '__construct');
        self::assertSame(8, $pool->getParameters()[1]->getDefaultValue());
    }

    /**
     * read(1) допустим; read(0) — нет.
     */
    public function testReadLengthBoundary(): void
    {
        $stream = fopen('php://temp', 'r+b');
        self::assertNotFalse($stream);
        fwrite($stream, 'ab');
        rewind($stream);
        $connection = new StreamConnection($stream, 'tcp', 4, null);
        self::assertSame('a', $connection->read(1));
        $connection->close();
    }

    /**
     * Нулевой таймаут нормализуется; отрицательный тоже.
     */
    public function testZeroTimeoutNormalized(): void
    {
        $options = new ConnectionOptions(0.0, 0.0);
        self::assertSame(5.0, $options->connectTimeout());
        self::assertSame(30.0, $options->ioTimeout());
    }

    /**
     * strtolower в EndpointGuard.
     */
    public function testHostLowercased(): void
    {
        $guard = new EndpointGuard();
        self::assertSame('example.com', $guard->extractHost('TCP://ExAmPle.COM:443'));
        self::assertSame('2001:db8::1', $guard->extractHost('[2001:DB8::1]:80'));
    }

    /**
     * trim в normalize реестра.
     */
    public function testRegistryTrimsId(): void
    {
        $registry = new ProtocolRegistry();
        self::assertTrue($registry->has('  TCP  '));
        self::assertSame('tcp', $registry->get("\ttcp\n")->id());
    }

    /**
     * Инъекция зависимостей в ConnectManager (против coalesce-swap).
     */
    public function testConnectManagerUsesInjectedCollaborators(): void
    {
        $registry = new ProtocolRegistry();
        $factory = new class () implements ConnectionFactoryInterface {
            #[\Override]
            public function connect(
                string $protocolId,
                string $endpoint,
                ?ConnectionOptions $options = null,
            ): ConnectionInterface {
                return new LogicalConnection($protocolId, 0, $endpoint, ['injected' => true]);
            }
        };
        $pool = new class ($factory) implements ConnectionPoolInterface {
            public function __construct(private readonly ConnectionFactoryInterface $factory)
            {
            }

            #[\Override]
            public function acquire(
                string $protocolId,
                string $endpoint,
                ?ConnectionOptions $options = null,
            ): ConnectionInterface {
                return $this->factory->connect($protocolId, $endpoint, $options);
            }

            #[\Override]
            public function release(ConnectionInterface $connection): void
            {
                // stub: пул-заглушка не хранит соединения
                unset($connection);
            }

            #[\Override]
            public function drain(): void
            {
                // stub: нечего освобождать
            }

            #[\Override]
            public function size(): int
            {
                return 42;
            }
        };
        $async = new class () implements AsyncRuntimeInterface {
            #[\Override]
            public function run(callable $task): mixed
            {
                return 'async-injected';
            }

            #[\Override]
            public function awaitReadable(mixed $stream, float $timeoutSeconds): void
            {
                // stub: async-заглушка не ждёт I/O
                unset($stream, $timeoutSeconds);
            }

            #[\Override]
            public function awaitWritable(mixed $stream, float $timeoutSeconds): void
            {
                // stub: async-заглушка не ждёт I/O
                unset($stream, $timeoutSeconds);
            }
        };

        $manager = new ConnectManager($registry, $factory, $pool, $async);
        self::assertSame($registry, $manager->protocols());
        self::assertSame(42, $manager->pool()->size());
        self::assertSame('async-injected', $manager->async()->run(static fn (): int => 1));
        $conn = $manager->connect('adsl', 'x', ConnectionOptions::defaults()->allowPrivateAddresses());
        self::assertTrue($conn->metadata()['injected']);
        $conn->close();
    }

    /**
     * LogicalConnection metadata attached=true.
     */
    public function testLogicalAttachMetadata(): void
    {
        $connection = new LogicalConnection('can', 0, null);
        $stream = fopen('php://temp', 'r+b');
        self::assertNotFalse($stream);
        $connection->attach($stream);
        self::assertTrue($connection->metadata()['attached']);
        $connection->close();
    }

    /**
     * ConnectionFactory metadata reason/native.
     */
    public function testFactoryLogicalMetadataKeys(): void
    {
        $factory = new ConnectionFactory(new ProtocolRegistry());
        $conn = $factory->connect('adsl', 'line', ConnectionOptions::defaults()->allowPrivateAddresses());
        $meta = $conn->metadata();
        self::assertArrayHasKey('reason', $meta);
        self::assertArrayHasKey('transport', $meta);
        self::assertFalse($meta['native']);
        $conn->close();
    }

    /**
     * unix path без id unix, но с /.
     */
    public function testResolverUnixPathBySlash(): void
    {
        $resolver = new TransportResolver();
        $tcp = new ProtocolDefinition('tcp', 'TCP', 4, 'tcp', true, 'tcp');
        self::assertSame('unix:///tmp/x.sock', $resolver->resolve($tcp, '/tmp/x.sock'));
    }

    /**
     * awaitWritable реально вызывается.
     */
    public function testAwaitWritableWorks(): void
    {
        $runtime = new FiberAsyncRuntime();
        $pipes = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($pipes);
        $runtime->awaitWritable($pipes[0], 1.0);
        fwrite($pipes[0], 'w');
        self::assertSame('w', fread($pipes[1], 1));
        fclose($pipes[0]);
        fclose($pipes[1]);
    }

    /**
     * isOpen требует и флаг, и resource.
     */
    public function testIsOpenRequiresBoth(): void
    {
        $stream = fopen('php://temp', 'r+b');
        self::assertNotFalse($stream);
        $connection = new StreamConnection($stream, 'tcp', 4, null);
        self::assertTrue($connection->isOpen());
        $connection->close();
        self::assertFalse($connection->isOpen());
    }

    /**
     * Pool fallback maxPerKey при 0.
     */
    public function testPoolDefaultMaxWhenZero(): void
    {
        $factory = new class () implements ConnectionFactoryInterface {
            #[\Override]
            public function connect(
                string $protocolId,
                string $endpoint,
                ?ConnectionOptions $options = null,
            ): ConnectionInterface {
                return new LogicalConnection($protocolId, 4, $endpoint);
            }
        };
        $pool = new ConnectionPool($factory, 0);
        $ref = new \ReflectionProperty($pool, 'maxPerKey');
        self::assertSame(8, $ref->getValue($pool));
    }
}

<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\Async\FiberAsyncRuntime;
use CloudCastle\ConnectManager\Async\NativeStreamSelector;
use CloudCastle\ConnectManager\Connection\LogicalConnection;
use CloudCastle\ConnectManager\Connection\StreamConnection;
use CloudCastle\ConnectManager\ConnectManager;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
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
use CloudCastle\ConnectManager\Tests\Support\EmptyChunkStreamWrapper;
use CloudCastle\ConnectManager\Tests\Support\FailingStreamWrapper;
use CloudCastle\ConnectManager\Tests\Support\MixedValues;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Ошибки I/O через failing stream wrapper.
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
/**
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.ShortVariable")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.MissingImport")
 * @SuppressWarnings("PHPMD.ExcessivePublicCount")
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 * @SuppressWarnings("PHPMD.ExcessiveClassLength")
 * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
 */
final class StreamIoFailureTest extends TestCase
{
    /**
     * fwrite/fread false → ConnectionException.
     */
    public function testStreamWriteReadFailures(): void
    {
        FailingStreamWrapper::register();
        $stream = fopen('ccfail://x', 'r+b');
        self::assertNotFalse($stream);
        $connection = new StreamConnection($stream, 'tcp', 4, null);

        try {
            $connection->write('x');
            self::fail('write');
        } catch (ConnectionException $exception) {
            self::assertStringContainsString('записи', $exception->getMessage());
        }

        try {
            $connection->read(1);
            self::fail('read');
        } catch (ConnectionException $exception) {
            self::assertStringContainsString('чтения', $exception->getMessage());
        }

        try {
            iterator_to_array($connection->readChunks(1));
            self::fail('chunks');
        } catch (ConnectionException $exception) {
            self::assertStringContainsString('чанка', $exception->getMessage());
        }

        $connection->close();
    }

    /**
     * LogicalConnection: write/read/chunks failures.
     */
    public function testLogicalWriteReadFailures(): void
    {
        FailingStreamWrapper::register();
        $connection = new LogicalConnection('adsl', 1, 'x');
        $stream = fopen('ccfail://y', 'r+b');
        self::assertNotFalse($stream);
        $connection->attach($stream);

        try {
            $connection->write('z');
            self::fail('write');
        } catch (ConnectionException $exception) {
            self::assertStringContainsString('записи', $exception->getMessage());
        }

        try {
            $connection->read(1);
            self::fail('read');
        } catch (ConnectionException $exception) {
            self::assertStringContainsString('чтения', $exception->getMessage());
        }

        $this->expectException(ConnectionException::class);
        iterator_to_array($connection->readChunks(1));
    }

    /**
     * TransportResolver: transport ip/socket вне карты схем.
     */
    public function testResolverEdgeCases(): void
    {
        $resolver = new TransportResolver();
        $customIp = new ProtocolDefinition('customip', 'Custom IP', 3, 'ip', true, 'customip');
        self::assertSame('tcp://example.com', $resolver->resolve($customIp, 'example.com'));

        $customSock = new ProtocolDefinition('rawsock', 'Raw sock', 0, 'socket', true, 'rawsock');
        self::assertSame('tcp://example.com:1', $resolver->resolve($customSock, 'example.com:1'));

        $icmp = new ProtocolDefinition('icmp', 'ICMP', 3, 'raw', true, 'icmp');

        $this->expectException(ProtocolException::class);
        $resolver->resolve($icmp, '8.8.8.8');
    }

    /**
     * Async wait на не-resource.
     */
    public function testAsyncWaitNonResource(): void
    {
        $runtime = new FiberAsyncRuntime();

        $this->expectException(TimeoutException::class);
        $runtime->awaitReadable(MixedValues::notAStream(), 0.1);
    }

    /**
     * Fiber::suspend с некорректным payload → resume(null).
     */
    public function testAsyncRunWithInvalidSuspendPayload(): void
    {
        $runtime = new FiberAsyncRuntime();
        $result = $runtime->run(static function (): string {
            \Fiber::suspend('not-array');

            return 'done';
        });

        self::assertSame('done', $result);
    }

    /**
     * Неизвестная операция ожидания внутри Fiber.
     */
    public function testAsyncUnknownWaitOp(): void
    {
        $runtime = new FiberAsyncRuntime();
        $pipes = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($pipes);

        try {
            $runtime->run(static function () use ($pipes): void {
                \Fiber::suspend([
                    'op' => 'bad',
                    'stream' => $pipes[0],
                    'timeout' => 0.1,
                ]);
            });
            self::fail('expected TimeoutException');
        } catch (TimeoutException $exception) {
            self::assertStringContainsString('Неизвестная', $exception->getMessage());
        } finally {
            fclose($pipes[0]);
            fclose($pipes[1]);
        }
    }

    /**
     * Пустой chunk прерывает генератор.
     */
    public function testEmptyChunkBreaksGenerator(): void
    {
        EmptyChunkStreamWrapper::register();
        $stream = fopen('ccempty://z', 'rb');
        self::assertNotFalse($stream);
        $connection = new StreamConnection($stream, 'tcp', 4, null);
        $chunks = iterator_to_array($connection->readChunks(8));
        self::assertSame([], $chunks);
        $connection->close();
    }

    /**
     * extractHost для URI без host.
     */
    public function testExtractHostWithoutHost(): void
    {
        $guard = new EndpointGuard();
        self::assertNull($guard->extractHost('tcp://'));
        self::assertNull($guard->extractHost('http:///no-host'));
        self::assertNull($guard->extractHost('http://:'));
    }

    /**
     * Классификация таймаута opener.
     */
    public function testOpenerTimeoutDetection(): void
    {
        $opener = new StreamOpener();
        self::assertTrue($opener->isTimeoutError(SOCKET_ETIMEDOUT, ''));
        self::assertTrue($opener->isTimeoutError(0, 'Connection TIMED OUT'));
        self::assertFalse($opener->isTimeoutError(0, 'Connection refused'));

        $opts = $opener->buildContextOptions(ConnectionOptions::defaults());
        self::assertIsArray($opts['ssl']);
        $ssl = $opts['ssl'];
        self::assertTrue($ssl['verify_peer']);
        self::assertSame(2, $ssl['security_level']);

        $insecureOpts = $opener->buildContextOptions(new ConnectionOptions(5.0, 30.0, false, true, true));
        self::assertIsArray($insecureOpts['ssl']);
        $insecureSsl = $insecureOpts['ssl'];
        self::assertFalse($insecureSsl['verify_peer']);
        self::assertSame(0, $insecureSsl['security_level']);

        $memory = fopen('php://memory', 'r+b');
        self::assertNotFalse($memory);
        $opener->applyIoTimeout($memory, 0.5);
        $opener->applyIoTimeout($memory, 2.0);
        fclose($memory);

        try {
            $opener->applyIoTimeout(MixedValues::notAStream(), 1.0);
            self::fail('applyIoTimeout');
        } catch (ConnectionException $exception) {
            self::assertStringContainsString('stream', $exception->getMessage());
        }

        try {
            $opener->createOpenException('tcp://x', SOCKET_ETIMEDOUT, '');
            self::fail('timeout');
        } catch (TimeoutException $exception) {
            self::assertStringContainsString('Таймаут', $exception->getMessage());
        }

        $connectionException = $opener->createOpenException('tcp://x', 111, 'refused');
        self::assertStringContainsString('refused', $connectionException->getMessage());
    }

    /**
     * Неполный / нестроковый payload suspend.
     */
    public function testAsyncIncompletePayloads(): void
    {
        $runtime = new FiberAsyncRuntime();

        $result = $runtime->run(static function (): string {
            \Fiber::suspend(['op' => 'read']);
            \Fiber::suspend(['op' => 1, 'stream' => 's', 'timeout' => 1.0]);
            \Fiber::suspend(['op' => 'read', 'stream' => 's', 'timeout' => 'x']);

            return 'ok';
        });

        self::assertSame('ok', $result);

        $method = new \ReflectionMethod(FiberAsyncRuntime::class, 'isWaitPayload');
        self::assertFalse($method->invoke($runtime, ['op' => 1, 'stream' => 's', 'timeout' => 1.0]));
        self::assertFalse($method->invoke($runtime, ['op' => 'read', 'stream' => 's', 'timeout' => 'x']));
        self::assertTrue($method->invoke($runtime, ['op' => 'read', 'stream' => 's', 'timeout' => 1]));
    }
}

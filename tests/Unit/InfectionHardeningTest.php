<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\Async\FiberAsyncRuntime;
use CloudCastle\ConnectManager\Async\NativeStreamSelector;
use CloudCastle\ConnectManager\Connection\LogicalConnection;
use CloudCastle\ConnectManager\Connection\StreamConnection;
use CloudCastle\ConnectManager\ConnectManager;
use CloudCastle\ConnectManager\Contract\ConnectionFactoryInterface;
use CloudCastle\ConnectManager\Contract\ConnectionInterface;
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
use CloudCastle\ConnectManager\Tests\Support\FixedStreamSelector;
use CloudCastle\ConnectManager\Tests\Support\MixedValues;
use CloudCastle\ConnectManager\Tests\Support\RecordingStreamSelector;
use CloudCastle\ConnectManager\Tests\Support\TrackableConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Жёсткие кейсы против escaped-мутантов Infection.
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.ShortVariable")
 * @SuppressWarnings("PHPMD.ExcessiveClassLength")
 * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 * @SuppressWarnings("PHPMD.TooManyMethods")
 * @SuppressWarnings("PHPMD.ExcessivePublicCount")
 * @SuppressWarnings("PHPMD.MissingImport")
 * @SuppressWarnings("PHPMD.UnusedFormalParameter")
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
final class InfectionHardeningTest extends TestCase
{
    /**
     * awaitWritable обязан ждать; иначе таймаут на заполненном pipe не случится.
     */
    public function testAwaitWritableTimesOutOnFullPipe(): void
    {
        $runtime = new FiberAsyncRuntime();
        $pipes = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($pipes);
        stream_set_blocking($pipes[1], false);
        $payload = str_repeat('x', 65536);

        while (true) {
            $written = fwrite($pipes[1], $payload);

            if ($written === false || $written === 0) {
                break;
            }
        }

        stream_set_blocking($pipes[1], true);
        $this->expectException(TimeoutException::class);

        try {
            $runtime->awaitWritable($pipes[1], 0.05);
        } finally {
            fclose($pipes[0]);
            fclose($pipes[1]);
        }
    }

    /**
     * Int-timeout в payload проходит через (float) cast.
     */
    public function testFiberIntTimeoutCast(): void
    {
        $runtime = new FiberAsyncRuntime();
        $pipes = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($pipes);
        fwrite($pipes[1], 'z');

        $result = $runtime->run(static function () use ($pipes): string {
            \Fiber::suspend(['op' => 'read', 'stream' => $pipes[0], 'timeout' => 2]);
            $data = fread($pipes[0], 1);

            return is_string($data) ? $data : '';
        });

        fclose($pipes[0]);
        fclose($pipes[1]);
        self::assertSame('z', $result);
    }

    /**
     * Неполный payload: каждая проверка key независима (LogicalOr).
     */
    public function testWaitPayloadRequiresAllKeysIndependently(): void
    {
        $runtime = new FiberAsyncRuntime();
        $seen = [];

        $runtime->run(static function () use (&$seen): int {
            \Fiber::suspend(['stream' => null, 'timeout' => 1.0]);
            $seen[] = 'missing-op';
            \Fiber::suspend(['op' => 'read', 'timeout' => 1.0]);
            $seen[] = 'missing-stream';
            \Fiber::suspend(['op' => 'read', 'stream' => null]);
            $seen[] = 'missing-timeout';

            return count($seen);
        });

        self::assertSame(['missing-op', 'missing-stream', 'missing-timeout'], $seen);
    }

    /**
     * Селектор false → ошибка ожидания; 0 → таймаут.
     */
    public function testSelectorFalseAndZero(): void
    {
        $runtime = new FiberAsyncRuntime(new FixedStreamSelector(false));
        $pipes = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($pipes);

        try {
            $runtime->awaitReadable($pipes[0], 1.0);
            self::fail('false select');
        } catch (TimeoutException $exception) {
            self::assertStringContainsString('Ошибка ожидания', $exception->getMessage());
        }

        $runtimeZero = new FiberAsyncRuntime(new FixedStreamSelector(0));

        try {
            $runtimeZero->awaitReadable($pipes[0], 1.0);
            self::fail('zero select');
        } catch (TimeoutException $exception) {
            self::assertStringContainsString('Таймаут ожидания', $exception->getMessage());
        }

        fclose($pipes[0]);
        fclose($pipes[1]);
    }

    /**
     * Неизвестный op и не-resource.
     */
    public function testWaitUnknownOpAndBadStream(): void
    {
        $runtime = new FiberAsyncRuntime();
        $pipes = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($pipes);

        try {
            $runtime->run(static function () use ($pipes): void {
                \Fiber::suspend(['op' => 'flush', 'stream' => $pipes[0], 'timeout' => 1.0]);
            });
            self::fail('unknown op');
        } catch (TimeoutException $exception) {
            self::assertStringContainsString('Неизвестная', $exception->getMessage());
        } finally {
            fclose($pipes[0]);
            fclose($pipes[1]);
        }

        $this->expectException(TimeoutException::class);
        $runtime->awaitReadable(MixedValues::notAStream(), 1.0);
    }

    /**
     * StreamOpener: успешный open + null code/message + context merge.
     */
    public function testStreamOpenerSuccessAndNulls(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($server);
        $name = stream_socket_get_name($server, false);
        self::assertIsString($name);

        $opener = new StreamOpener();
        $options = ConnectionOptions::defaults()->allowPrivateAddresses();
        $stream = $opener->open('tcp://' . $name, $options);
        $meta = stream_get_meta_data($stream);
        self::assertTrue($meta['blocked']);
        fclose($stream);
        fclose($server);

        $nullException = $opener->createOpenException('tcp://x', null, null);
        self::assertStringContainsString('[0]', $nullException->getMessage());

        $merged = $opener->buildContextOptions(new ConnectionOptions(
            3.0,
            4.0,
            true,
            false,
            false,
            ['ssl' => ['peer_name' => 'example.test'], 'socket' => ['tcp_nodelay' => false]],
        ));
        self::assertArrayHasKey('socket', $merged);
        self::assertArrayHasKey('ssl', $merged);
        /** @var array<string, mixed> $socketOpts */
        $socketOpts = $merged['socket'];
        /** @var array<string, mixed> $sslOpts */
        $sslOpts = $merged['ssl'];
        self::assertFalse($socketOpts['tcp_nodelay']);
        self::assertSame('example.test', $sslOpts['peer_name']);
        self::assertTrue($sslOpts['verify_peer']);
        self::assertSame(2, $sslOpts['security_level']);

        $memory = fopen('php://memory', 'r+b');
        self::assertNotFalse($memory);
        $opener->applyIoTimeout($memory, 0.25);
        fclose($memory);
    }

    /**
     * applyIoTimeout на не-resource покрывает throw.
     */
    public function testApplyIoTimeoutRejectsNonResource(): void
    {
        $opener = new StreamOpener();
        $this->expectException(ConnectionException::class);
        $opener->applyIoTimeout(MixedValues::notAStream(), 1.0);
    }

    /**
     * Factory metadata содержит все ключи; native false для adsl.
     */
    public function testFactoryMetadataKeys(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($server);
        $name = stream_socket_get_name($server, false);
        self::assertIsString($name);
        $hostPort = str_replace('tcp://', '', $name);

        $factory = new ConnectionFactory(new ProtocolRegistry());
        $conn = $factory->connect('tcp', $hostPort, ConnectionOptions::defaults()->allowPrivateAddresses());
        $meta = $conn->metadata();
        self::assertArrayHasKey('uri', $meta);
        self::assertArrayHasKey('transport', $meta);
        self::assertArrayHasKey('native', $meta);
        self::assertArrayHasKey('mode', $meta);
        self::assertTrue($meta['native']);
        self::assertSame('sync', $meta['mode']);
        $conn->close();
        fclose($server);

        $logical = $factory->connect('adsl', 'line', ConnectionOptions::defaults()->allowPrivateAddresses());
        self::assertFalse($logical->metadata()['native']);
        self::assertArrayHasKey('reason', $logical->metadata());
        self::assertArrayHasKey('transport', $logical->metadata());
    }

    /**
     * TransportResolver: edge ports, ip/socket/raw, native custom.
     */
    public function testTransportResolverEdges(): void
    {
        $resolver = new TransportResolver();
        $http = new ProtocolDefinition('http', 'HTTP', 7, 'tcp', true, 'http');
        $tcp = new ProtocolDefinition('tcp', 'TCP', 4, 'tcp', true, 'tcp');
        $ip = new ProtocolDefinition('custom-ip', 'IP', 3, 'ip', true, 'custom-ip');
        $sock = new ProtocolDefinition('custom-sock', 'S', 4, 'socket', true, 'custom-sock');
        $raw = new ProtocolDefinition('serial', 'Serial', 1, 'serial', true, 'serial');
        $nativeUnknown = new ProtocolDefinition('fooproto', 'Foo', 7, 'tcp', true, 'fooproto');

        self::assertSame('tcp://h:http:80', $resolver->resolve($http, 'h:http'));
        self::assertSame('tcp://h:80', $resolver->resolve($http, 'h'));
        self::assertSame('tcp://h:9', $resolver->resolve($tcp, 'h:9'));
        self::assertSame('tcp://[::1]:80', $resolver->resolve($http, '[::1]'));
        self::assertSame('tcp://[::1]:443', $resolver->resolve($tcp, '[::1]:443'));
        self::assertSame('tcp://x:1', $resolver->resolve($ip, 'x:1'));
        self::assertSame('tcp://x:1', $resolver->resolve($sock, 'x:1'));
        self::assertSame('tcp://z:1', $resolver->resolve($nativeUnknown, 'z:1'));

        $this->expectException(ProtocolException::class);
        $resolver->resolve($raw, 'com1');
    }

    /**
     * Пул: регистр ключа, drain закрывает, close мёртвых при acquire.
     */
    public function testPoolKeyCaseAndDeadClose(): void
    {
        $closes = 0;
        $factory = new class ($closes) implements ConnectionFactoryInterface {
            /** @var callable(): void */
            private $bump;

            public function __construct(int &$closes)
            {
                $this->bump = static function () use (&$closes): void {
                    ++$closes;
                };
            }

            #[\Override]
            public function connect(
                string $protocolId,
                string $endpoint,
                ?ConnectionOptions $options = null,
            ): ConnectionInterface {
                unset($options);
                $bump = $this->bump;

                return new TrackableConnection($protocolId, $endpoint, static function () use ($bump): void {
                    $bump();
                });
            }
        };

        $pool = new ConnectionPool($factory, 4);
        $a = $pool->acquire('TCP', 'Host:1');
        $pool->release($a);
        $b = $pool->acquire('tcp', 'Host:1');
        self::assertSame($a, $b);

        $keyMethod = new ReflectionMethod(ConnectionPool::class, 'key');
        self::assertSame('tcp|Host:1', $keyMethod->invoke($pool, 'TCP', 'Host:1'));

        $dead = $pool->acquire('tcp', 'Host:1');
        self::assertInstanceOf(TrackableConnection::class, $dead);
        $dead->markDead();

        $idle = new ReflectionProperty(ConnectionPool::class, 'idle');
        /** @var array<string, list<ConnectionInterface>> $buckets */
        $buckets = $idle->getValue($pool);
        $buckets['tcp|Host:1'] = [$dead];
        $idle->setValue($pool, $buckets);

        $before = $closes;
        $fresh = $pool->acquire('tcp', 'Host:1');
        self::assertNotSame($dead, $fresh);
        self::assertGreaterThan($before, $closes);

        $pool->release($fresh);
        $pool->drain();
        self::assertSame(0, $pool->size());
    }

    /**
     * connectAsync закрывает соединение в finally даже при исключении.
     */
    public function testConnectAsyncFinallyCloses(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($server);
        $name = stream_socket_get_name($server, false);
        self::assertIsString($name);
        $hostPort = str_replace('tcp://', '', $name);
        $manager = new ConnectManager();
        /** @var ConnectionInterface|null $held */
        $held = null;

        try {
            $manager->connectAsync(
                'tcp',
                $hostPort,
                static function (ConnectionInterface $connection) use (&$held): never {
                    $held = $connection;

                    throw new \RuntimeException('boom');
                },
                ConnectionOptions::defaults()->allowPrivateAddresses(),
            );
            self::fail('expected');
        } catch (\RuntimeException $exception) {
            self::assertSame('boom', $exception->getMessage());
            self::assertInstanceOf(ConnectionInterface::class, $held);
            self::assertFalse($held->isOpen());
        }

        fclose($server);
    }

    /**
     * EndpointGuard: host:port и IPv6.
     */
    public function testEndpointGuardHostPort(): void
    {
        $guard = new EndpointGuard();
        self::assertSame('example.com', $guard->extractHost('example.com:443'));
        self::assertSame('2001:db8::1', $guard->extractHost('[2001:db8::1]:443'));
        self::assertNull($guard->extractHost('[broken'));
        self::assertSame('abc', $guard->extractHost('ABC'));
    }

    /**
     * Logical/Stream: границы chunk и close без resource.
     */
    public function testChunkBoundariesAndClose(): void
    {
        $logical = new LogicalConnection('tcp', 4, 'x');
        $this->expectException(ConnectionException::class);
        $logical->read(0);
    }

    /**
     * StreamConnection requireStream после close.
     */
    public function testStreamRequireAfterClose(): void
    {
        $stream = fopen('php://memory', 'r+b');
        self::assertNotFalse($stream);
        $connection = new StreamConnection($stream, 'tcp', 4, 'e');
        $connection->close();
        self::assertFalse($connection->isOpen());
        $this->expectException(ConnectionException::class);
        $connection->read(1);
    }

    /**
     * NativeStreamSelector пропускает готовый pipe.
     */
    public function testNativeSelectorReady(): void
    {
        $selector = new NativeStreamSelector();
        $pipes = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($pipes);
        fwrite($pipes[1], '1');
        $read = [$pipes[0]];
        $write = null;
        $except = null;
        $result = $selector->select($read, $write, $except, 1);
        self::assertNotFalse($result);
        self::assertGreaterThan(0, $result);
        fclose($pipes[0]);
        fclose($pipes[1]);
    }

    /**
     * Fiber suspend вызывает select; min timeout = 1 секунда.
     */
    public function testFiberSuspendInvokesSelectorWithMinSecond(): void
    {
        $selector = new RecordingStreamSelector(1);
        $runtime = new FiberAsyncRuntime($selector);
        $pipes = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($pipes);

        $runtime->run(static function () use ($runtime, $pipes): void {
            $runtime->awaitReadable($pipes[0], 0.25);
        });

        self::assertSame(1, $selector->calls);
        self::assertSame(1, $selector->lastSeconds);
        self::assertFalse($selector->calledInsideFiber);
        fclose($pipes[0]);
        fclose($pipes[1]);
    }

    /**
     * Размер чанка по умолчанию 8192; readChunks(1) валиден.
     */
    public function testDefaultChunkSizeIsExact(): void
    {
        $stream = fopen('php://memory', 'r+b');
        self::assertNotFalse($stream);
        fwrite($stream, str_repeat('x', 9000));
        rewind($stream);
        $connection = new StreamConnection($stream, 'tcp', 4, null);
        $chunks = iterator_to_array($connection->readChunks());
        self::assertSame(8192, strlen($chunks[0]));
        self::assertSame(9000 - 8192, strlen($chunks[1]));
        $connection->close();

        $logical = new LogicalConnection('tcp', 4, 'x');
        $stream2 = fopen('php://memory', 'r+b');
        self::assertNotFalse($stream2);
        fwrite($stream2, str_repeat('y', 9000));
        rewind($stream2);
        $logical->attach($stream2);
        $logicalChunks = iterator_to_array($logical->readChunks());
        self::assertSame(8192, strlen($logicalChunks[0]));
        $logical->close();

        $logicalTiny = new LogicalConnection('tcp', 4, 'x');
        $stream3 = fopen('php://memory', 'r+b');
        self::assertNotFalse($stream3);
        fwrite($stream3, 'z');
        rewind($stream3);
        $logicalTiny->attach($stream3);
        self::assertSame(['z'], iterator_to_array($logicalTiny->readChunks(1)));
        $logicalTiny->close();
    }

    /**
     * resolveIoTimeoutSeconds: минимум 1.
     */
    public function testResolveIoTimeoutSeconds(): void
    {
        $opener = new StreamOpener();
        self::assertSame(1, $opener->resolveIoTimeoutSeconds(0.25));
        self::assertSame(1, $opener->resolveIoTimeoutSeconds(0.0));
        self::assertSame(5, $opener->resolveIoTimeoutSeconds(5.9));
    }

    /**
     * SSL context keys обязательны; failed open бросает.
     */
    public function testOpenerContextKeysAndFailedOpen(): void
    {
        $opener = new StreamOpener();
        $opts = $opener->buildContextOptions(ConnectionOptions::defaults());
        self::assertArrayHasKey('socket', $opts);
        self::assertArrayHasKey('ssl', $opts);
        /** @var array<string, mixed> $socket */
        $socket = $opts['socket'];
        /** @var array<string, mixed> $ssl */
        $ssl = $opts['ssl'];
        self::assertTrue($socket['tcp_nodelay']);
        self::assertArrayHasKey('verify_peer_name', $ssl);
        self::assertTrue($ssl['verify_peer_name']);
        self::assertFalse($ssl['allow_self_signed']);

        $this->expectException(ConnectionException::class);
        $opener->open('tcp://127.0.0.1:1', ConnectionOptions::defaults()->allowPrivateAddresses());
    }

    /**
     * Logical requireAttached после close.
     */
    public function testLogicalRequireAfterClose(): void
    {
        $connection = new LogicalConnection('tcp', 4, 'x');
        $stream = fopen('php://memory', 'r+b');
        self::assertNotFalse($stream);
        $connection->attach($stream);
        $connection->close();
        $this->expectException(ConnectionException::class);
        $connection->read(1);
    }

    /**
     * Injected guard/resolver/opener сохраняются как есть.
     */
    public function testFactoryKeepsInjectedDeps(): void
    {
        $guard = new EndpointGuard();
        $resolver = new TransportResolver();
        $opener = new StreamOpener();
        $factory = new ConnectionFactory(new ProtocolRegistry(), $guard, $resolver, $opener);
        self::assertSame($guard, (new ReflectionProperty(ConnectionFactory::class, 'guard'))->getValue($factory));
        self::assertSame($resolver, (new ReflectionProperty(ConnectionFactory::class, 'resolver'))->getValue($factory));
        self::assertSame($opener, (new ReflectionProperty(ConnectionFactory::class, 'opener'))->getValue($factory));
    }

    /**
     * drain закрывает idle; size суммирует несколько bucket.
     */
    public function testPoolDrainAndMultiBucketSize(): void
    {
        $closes = 0;
        $factory = new class ($closes) implements ConnectionFactoryInterface {
            /** @var callable(): void */
            private $bump;

            public function __construct(int &$closes)
            {
                $this->bump = static function () use (&$closes): void {
                    ++$closes;
                };
            }

            #[\Override]
            public function connect(
                string $protocolId,
                string $endpoint,
                ?ConnectionOptions $options = null,
            ): ConnectionInterface {
                unset($options);
                $bump = $this->bump;

                return new TrackableConnection($protocolId, $endpoint, static function () use ($bump): void {
                    $bump();
                });
            }
        };

        $pool = new ConnectionPool($factory, 4);
        $a = $pool->acquire('tcp', 'a:1');
        $b = $pool->acquire('tcp', 'b:1');
        $pool->release($a);
        $pool->release($b);
        self::assertSame(2, $pool->size());
        $before = $closes;
        $pool->drain();
        self::assertSame(0, $pool->size());
        self::assertSame($before + 2, $closes);
        self::assertFalse($a->isOpen());
        self::assertFalse($b->isOpen());
    }

    /**
     * EndpointGuard: нецифровой порт не отрезается; LogicalAnd vs Or.
     */
    public function testEndpointGuardNonDigitPortKept(): void
    {
        $guard = new EndpointGuard();
        self::assertSame('host:abc', $guard->extractHost('host:abc'));
        self::assertSame('example.com', $guard->extractHost('example.com'));
    }

    /**
     * Logical read(1) допустим; readChunks default через reflection.
     */
    public function testLogicalReadOneAndDefaultChunk(): void
    {
        $connection = new LogicalConnection('tcp', 4, 'x');
        $stream = fopen('php://memory', 'r+b');
        self::assertNotFalse($stream);
        fwrite($stream, 'ab');
        rewind($stream);
        $connection->attach($stream);
        self::assertSame('a', $connection->read(1));
        $method = new ReflectionMethod(LogicalConnection::class, 'readChunks');
        self::assertSame(8192, $method->getParameters()[0]->getDefaultValue());
        $connection->close();
    }

    /**
     * isOpen требует и open, и resource.
     */
    public function testStreamIsOpenConjunction(): void
    {
        $stream = fopen('php://memory', 'r+b');
        self::assertNotFalse($stream);
        $connection = new StreamConnection($stream, 'tcp', 4, null);
        $connection->close();
        $open = new ReflectionProperty(StreamConnection::class, 'open');
        $open->setValue($connection, true);
        self::assertFalse($connection->isOpen());
    }

    /**
     * Transport: tcp без порта, blocked schemes, non-native.
     */
    public function testTransportMoreEdges(): void
    {
        $resolver = new TransportResolver();
        $tcp = new ProtocolDefinition('tcp', 'TCP', 4, 'tcp', true, 'tcp');
        self::assertSame('tcp://onlyhost', $resolver->resolve($tcp, 'onlyhost'));

        foreach (['none', 'raw', 'serial', 'custom', 'icmp'] as $transport) {
            $proto = new ProtocolDefinition('x-' . $transport, 'X', 1, $transport, true, 'x-' . $transport);

            try {
                $resolver->resolve($proto, 'h:1');
                self::fail($transport);
            } catch (ProtocolException $exception) {
                self::assertStringContainsString($transport, $exception->getMessage());
            }
        }

        $nonNative = new ProtocolDefinition('adsl', 'ADSL', 1, 'none', false, 'adsl');
        $this->expectException(ProtocolException::class);
        $resolver->resolve($nonNative, 'line');
    }

    /**
     * Non-native вне SCHEME_MAP с transport=tcp обязан бросить до resolve.
     */
    public function testTransportNonNativeTcpTransportThrows(): void
    {
        $resolver = new TransportResolver();
        $weird = new ProtocolDefinition('weirdproto', 'W', 1, 'tcp', false, 'weirdproto');
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('не имеет нативного транспорта');
        $resolver->resolve($weird, 'h:1');
    }

    /**
     * hasExplicitPort: host:port не получает второй порт.
     */
    public function testTransportExplicitPortNotDoubled(): void
    {
        $resolver = new TransportResolver();
        $http = new ProtocolDefinition('http', 'HTTP', 7, 'tcp', true, 'http');
        self::assertSame('tcp://example.com:80', $resolver->resolve($http, 'example.com:80'));
        self::assertSame('tcp://example.com:9', $resolver->resolve($http, 'example.com:9'));
        self::assertSame('tcp://example.com::80', $resolver->resolve($http, 'example.com:'));
        self::assertSame('tls://[2001:db8::1]:443', $resolver->resolve(
            new ProtocolDefinition('https', 'HTTPS', 7, 'tcp', true, 'https'),
            '[2001:db8::1]:443',
        ));
        self::assertSame('tcp://[2001:db8::2]:80', $resolver->resolve($http, '[2001:db8::2]'));
    }

    /**
     * Logical: open=false при живом attach → «закрыто»; empty chunk break.
     */
    public function testLogicalClosedFlagAndEmptyChunk(): void
    {
        EmptyChunkStreamWrapper::register();
        $connection = new LogicalConnection('tcp', 4, 'x');
        $stream = fopen('ccempty://z', 'rb');
        self::assertNotFalse($stream);
        $connection->attach($stream);
        self::assertSame([], iterator_to_array($connection->readChunks(4)));

        $alive = new LogicalConnection('tcp', 4, 'x');
        $mem = fopen('php://memory', 'r+b');
        self::assertNotFalse($mem);
        fwrite($mem, 'ab');
        rewind($mem);
        $alive->attach($mem);
        $open = new ReflectionProperty(LogicalConnection::class, 'open');
        $open->setValue($alive, false);
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('закрыто');
        $alive->read(1);
    }

    /**
     * StreamConnection: chunkSize=1 ок; close обнуляет open и fclose; requireStream And/Or.
     */
    public function testStreamConnectionCloseAndChunkOne(): void
    {
        $stream = fopen('php://memory', 'r+b');
        self::assertNotFalse($stream);
        fwrite($stream, 'xy');
        rewind($stream);
        $connection = new StreamConnection($stream, 'tcp', 4, null);
        self::assertSame(['x', 'y'], iterator_to_array($connection->readChunks(1)));

        $raw = MixedValues::memoryStream();
        $connection2 = new StreamConnection($raw, 'tcp', 4, null);
        $connection2->close();
        $connection2->close();
        $open = new ReflectionProperty(StreamConnection::class, 'open');
        self::assertFalse($open->getValue($connection2));
        self::assertNull($connection2->resource());
        self::assertFalse(is_resource(MixedValues::loosen($raw)));

        $stream3 = fopen('php://memory', 'r+b');
        self::assertNotFalse($stream3);
        $connection3 = new StreamConnection($stream3, 'tcp', 4, null);
        $connection3->close();
        $open->setValue($connection3, true);
        $this->expectException(ConnectionException::class);
        $connection3->read(1);
    }

    /**
     * readChunks останавливается при close() (&&, не ||).
     */
    public function testReadChunksStopsAfterClose(): void
    {
        $stream = fopen('php://memory', 'r+b');
        self::assertNotFalse($stream);
        fwrite($stream, str_repeat('x', 100));
        rewind($stream);
        $connection = new StreamConnection($stream, 'tcp', 4, null);
        $got = [];

        foreach ($connection->readChunks(10) as $chunk) {
            $got[] = $chunk;
            $connection->close();
        }

        self::assertCount(1, $got);
        self::assertSame(10, strlen($got[0]));
    }

    /**
     * Opener: refused бросает ConnectionException; blocking и timeout применяются.
     */
    public function testOpenerRefusedAndBlockingTimeout(): void
    {
        $opener = new StreamOpener();
        $options = new ConnectionOptions(0.2, 7.0, true, false, false);

        try {
            $opener->open('tcp://127.0.0.1:1', $options->allowPrivateAddresses());
            self::fail('refused');
        } catch (ConnectionException $exception) {
            self::assertStringContainsString('Не удалось открыть', $exception->getMessage());
            self::assertNotSame('Не удалось открыть tcp://127.0.0.1:1: [0] ', $exception->getMessage());
        }

        $server = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($server);
        $name = stream_socket_get_name($server, false);
        self::assertIsString($name);
        $oneSecondIo = new ConnectionOptions(5.0, 1.0, true, false, false);
        $stream = $opener->open(
            'tcp://' . $name,
            $oneSecondIo->allowPrivateAddresses(),
        );
        $meta = stream_get_meta_data($stream);
        self::assertTrue($meta['blocked']);
        self::assertSame(1, $opener->applyIoTimeout($stream, 1.0));
        fclose($stream);
        fclose($server);

        $memory = fopen('php://memory', 'r+b');
        self::assertNotFalse($memory);
        self::assertSame(3, $opener->applyIoTimeout($memory, 3.0));
        fclose($memory);

        self::assertIsString($opener->lastErrorMessage());
    }
}

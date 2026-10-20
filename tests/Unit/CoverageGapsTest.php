<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\Async\FiberAsyncRuntime;
use CloudCastle\ConnectManager\Connection\LogicalConnection;
use CloudCastle\ConnectManager\ConnectManager;
use CloudCastle\ConnectManager\Contract\AuthCredentials;
use CloudCastle\ConnectManager\Contract\ConnectionFactoryInterface;
use CloudCastle\ConnectManager\Contract\ConnectionInterface;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Contract\SshConnectorInterface;
use CloudCastle\ConnectManager\Driver\ConnectionFactory;
use CloudCastle\ConnectManager\Driver\Http\HttpClient;
use CloudCastle\ConnectManager\Driver\Http\HttpRequest;
use CloudCastle\ConnectManager\Driver\Http\HttpResponse;
use CloudCastle\ConnectManager\Driver\Ssh\SftpClient;
use CloudCastle\ConnectManager\Driver\Ssh\SshBinary;
use CloudCastle\ConnectManager\Driver\Ssh\SshConnection;
use CloudCastle\ConnectManager\Driver\Ssh\SshPublicKeyAuth;
use CloudCastle\ConnectManager\Driver\StreamOpener;
use CloudCastle\ConnectManager\Exception\ConnectionException;
use CloudCastle\ConnectManager\Exception\SecurityException;
use CloudCastle\ConnectManager\Protocol\ProtocolRegistry;
use CloudCastle\ConnectManager\Security\EndpointGuard;
use CloudCastle\ConnectManager\Security\PinnedEndpoint;
use CloudCastle\ConnectManager\Tests\Support\BufferConnection;
use CloudCastle\ConnectManager\Tests\Support\FakeSshSession;
use CloudCastle\ConnectManager\Tests\Support\FixedDnsResolver;
use CloudCastle\ConnectManager\Tests\Support\IncrementalBufferConnection;
use CloudCastle\ConnectManager\Tests\Support\ZeroWriteConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Дополнительное покрытие веток, не учитываемых из-за UsesClass в других тестах.
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
#[CoversClass(PinnedEndpoint::class)]
#[CoversClass(EndpointGuard::class)]
#[CoversClass(ConnectManager::class)]
#[CoversClass(ConnectionFactory::class)]
#[CoversClass(HttpClient::class)]
#[CoversClass(HttpResponse::class)]
#[CoversClass(SshBinary::class)]
#[CoversClass(SshConnection::class)]
#[CoversClass(SftpClient::class)]
#[CoversClass(SshPublicKeyAuth::class)]
#[CoversClass(StreamOpener::class)]
#[CoversClass(FiberAsyncRuntime::class)]
#[CoversClass(\CloudCastle\ConnectManager\Security\PhpDnsResolver::class)]
#[UsesClass(\CloudCastle\ConnectManager\Contract\AuthCredentials::class)]
#[UsesClass(\CloudCastle\ConnectManager\Contract\ConnectionOptions::class)]
#[UsesClass(\CloudCastle\ConnectManager\Connection\LogicalConnection::class)]
#[UsesClass(\CloudCastle\ConnectManager\Driver\Http\HttpRequest::class)]
#[UsesClass(\CloudCastle\ConnectManager\Pool\ConnectionPool::class)]
#[UsesClass(\CloudCastle\ConnectManager\Protocol\ProtocolCatalog::class)]
#[UsesClass(\CloudCastle\ConnectManager\Protocol\ProtocolDefinition::class)]
#[UsesClass(\CloudCastle\ConnectManager\Protocol\ProtocolRegistry::class)]
#[UsesClass(\CloudCastle\ConnectManager\Async\NativeStreamSelector::class)]
#[UsesClass(\CloudCastle\ConnectManager\Connection\StreamConnection::class)]
#[UsesClass(\CloudCastle\ConnectManager\Driver\TransportResolver::class)]
final class CoverageGapsTest extends TestCase
{
    /**
     * Геттеры PinnedEndpoint после prepare().
     */
    public function testPinnedEndpointGetters(): void
    {
        $guard = new EndpointGuard(new FixedDnsResolver(['pin.example' => ['8.8.4.4', '8.8.8.8']]));
        $pinned = $guard->prepare('pin.example:443', ConnectionOptions::defaults());

        self::assertSame('8.8.4.4:443', $pinned->connectEndpoint());
        self::assertSame('pin.example', $pinned->tlsPeerName());
        self::assertSame(['8.8.4.4', '8.8.8.8'], $pinned->resolvedIps());

        $literal = $guard->prepare('8.8.4.4:53', ConnectionOptions::defaults());
        self::assertNull($literal->tlsPeerName());
        self::assertSame(['8.8.4.4'], $literal->resolvedIps());
    }

    /**
     * EndpointGuard: allowPrivate, assertConnectedPeer, replaceHost URL.
     */
    public function testEndpointGuardExtraBranches(): void
    {
        $guard = new EndpointGuard(new FixedDnsResolver());
        $privateBypass = $guard->prepare('127.0.0.1:9', ConnectionOptions::defaults()->allowPrivateAddresses());
        self::assertSame('127.0.0.1:9', $privateBypass->connectEndpoint());
        self::assertSame([], $privateBypass->resolvedIps());

        $server = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($server);
        $serverName = stream_socket_get_name($server, false);
        self::assertIsString($serverName);
        $client = stream_socket_client($serverName);
        self::assertNotFalse($client);
        $guard->assertConnectedPeer($client, ConnectionOptions::defaults()->allowPrivateAddresses());
        fclose($client);
        fclose($server);

        $guardBlocked = new EndpointGuard(new FixedDnsResolver(['peer.test' => ['10.0.0.1']]));
        $this->expectException(SecurityException::class);
        $guardBlocked->prepare('peer.test:80', ConnectionOptions::defaults());
    }

    /**
     * resolvePublicIps для частного IP.
     */
    public function testEndpointGuardBlocksPrivateIpLiteral(): void
    {
        $guard = new EndpointGuard(new FixedDnsResolver());
        $this->expectException(SecurityException::class);
        $guard->resolvePublicIps('192.168.1.1');
    }

    /**
     * ssh/sftp на фасаде и ошибка типа соединения.
     */
    public function testConnectManagerSshSftp(): void
    {
        $sshConn = new SshConnection(new FakeSshSession(), 'ssh', 'h:22');
        $connector = new class ($sshConn) implements SshConnectorInterface {
            public function __construct(private readonly SshConnection $connection)
            {
            }

            #[\Override]
            public function connect(
                string $protocolId,
                string $endpoint,
                ConnectionOptions $options,
            ): ConnectionInterface {
                unset($protocolId, $endpoint, $options);

                return $this->connection;
            }
        };
        $factory = new ConnectionFactory(new ProtocolRegistry(), ssh: $connector);
        $manager = new ConnectManager(factory: $factory);
        $auth = AuthCredentials::withPassword('u', 'p');
        $options = ConnectionOptions::defaults()->allowPrivateAddresses()->withAuth($auth);

        self::assertSame($sshConn, $manager->ssh('127.0.0.1:22', $auth, $options));
        $sftpConn = new SshConnection(new FakeSshSession(), 'sftp', 'h:22');
        $sftpFactory = new ConnectionFactory(
            new ProtocolRegistry(),
            ssh: new class ($sftpConn) implements SshConnectorInterface {
                public function __construct(private readonly SshConnection $connection)
                {
                }

                #[\Override]
                public function connect(
                    string $protocolId,
                    string $endpoint,
                    ConnectionOptions $options,
                ): ConnectionInterface {
                    unset($protocolId, $endpoint, $options);

                    return $this->connection;
                }
            },
        );
        (new ConnectManager(factory: $sftpFactory))->sftp('127.0.0.1:22', $auth, $options);

        $badFactory = new class () implements ConnectionFactoryInterface {
            #[\Override]
            public function connect(
                string $protocolId,
                string $endpoint,
                ?ConnectionOptions $options = null,
            ): ConnectionInterface {
                unset($protocolId, $endpoint, $options);

                return new LogicalConnection('ssh', 7, 'x');
            }
        };
        $badManager = new ConnectManager(factory: $badFactory);
        $this->expectException(ConnectionException::class);
        $badManager->ssh('127.0.0.1:22', $auth);
    }

    /**
     * ConnectionFactory: sftp, TLS peer_name, capability http.
     */
    public function testConnectionFactoryBranches(): void
    {
        $sshConn = new SshConnection(new FakeSshSession(), 'sftp', '127.0.0.1:22');
        $factory = new ConnectionFactory(
            new ProtocolRegistry(),
            new EndpointGuard(new FixedDnsResolver(['tls.example' => ['8.8.8.8']])),
            ssh: new class ($sshConn) implements SshConnectorInterface {
                public function __construct(private readonly SshConnection $connection)
                {
                }

                #[\Override]
                public function connect(
                    string $protocolId,
                    string $endpoint,
                    ConnectionOptions $options,
                ): ConnectionInterface {
                    unset($protocolId, $endpoint, $options);

                    return $this->connection;
                }
            },
        );
        self::assertSame(
            $sshConn,
            $factory->connect('sftp', '127.0.0.1:22', ConnectionOptions::defaults()->allowPrivateAddresses()),
        );

        $server = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($server);
        $hostPort = str_replace('tcp://', '', (string) stream_socket_get_name($server, false));
        $pinnedFactory = new ConnectionFactory(new ProtocolRegistry());
        $httpConn = $pinnedFactory->connect(
            'http',
            $hostPort,
            ConnectionOptions::defaults()->allowPrivateAddresses(),
        );
        self::assertSame('http-application', $httpConn->metadata()['capability']);
        $httpConn->close();
        fclose($server);

        $tlsFactory = new ConnectionFactory(
            new ProtocolRegistry(),
            new EndpointGuard(new FixedDnsResolver(['remote.test' => ['8.8.8.8']])),
        );

        try {
            $tlsFactory->connect('tcp', 'remote.test:9', ConnectionOptions::defaults());
            self::fail('ожидалось исключение connect');
        } catch (ConnectionException) {
        }
    }

    /**
     * HttpClient без фабрики.
     */
    public function testHttpClientSendRequiresFactory(): void
    {
        $this->expectException(ConnectionException::class);
        (new HttpClient())->send(HttpRequest::get('http://example.com/'));
    }

    /**
     * exchange() при write === 0.
     */
    public function testHttpClientWriteFailure(): void
    {
        $this->expectException(ConnectionException::class);
        (new HttpClient())->exchange(new ZeroWriteConnection(''), 'GET', '/');
    }

    /**
     * Chunked ответ и чтение до close без Content-Length.
     */
    public function testHttpClientChunkedAndReadUntilClose(): void
    {
        $client = new HttpClient();
        $chunked = new BufferConnection(
            "HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\nConnection: close\r\n\r\n"
            . "5\r\nhello\r\n0\r\n\r\n",
        );
        $chunkedRaw = $client->exchange($chunked, 'GET', '/');
        self::assertStringContainsString('hello', $chunkedRaw);

        $partial = new BufferConnection("HTTP/1.1 200 OK\r\nConnection: close\r\n\r\nbody");
        self::assertSame('body', HttpResponse::fromRaw($client->exchange($partial, 'GET', '/'))->body());

        $incremental = new IncrementalBufferConnection([
            "HTTP/1.1 200 OK\r\nContent-Length: 4\r\n\r\nab",
            'cd',
        ]);
        self::assertSame('abcd', HttpResponse::fromRaw($client->exchange($incremental, 'GET', '/'))->body());

        $emptyChunk = new IncrementalBufferConnection([
            "HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\na",
            '',
            'b',
        ]);
        self::assertSame('a', HttpResponse::fromRaw($client->exchange($emptyChunk, 'GET', '/'))->body());

        $headersOnly = new IncrementalBufferConnection([
            "HTTP/1.1 200 OK\r\nContent-Length: 1\r\n",
            "\r\n",
            'x',
        ]);
        self::assertSame('x', HttpResponse::fromRaw($client->exchange($headersOnly, 'GET', '/'))->body());
    }

    /**
     * HttpClient: пустой raw-ответ.
     */
    public function testHttpClientEmptyRawResponse(): void
    {
        $this->expectException(ConnectionException::class);
        (new HttpClient())->exchange(new BufferConnection(''), 'GET', '/');
    }

    /**
     * Некорректные URL и схемы.
     */
    public function testHttpClientBadUrls(): void
    {
        $client = new HttpClient(new class () implements ConnectionFactoryInterface {
            #[\Override]
            public function connect(
                string $protocolId,
                string $endpoint,
                ?ConnectionOptions $options = null,
            ): ConnectionInterface {
                unset($protocolId, $endpoint, $options);

                return new BufferConnection("HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n");
            }
        });

        $this->expectException(ConnectionException::class);
        $client->get('not-a-valid-url');
    }

    /**
     * HttpClient: неподдерживаемая схема URL.
     */
    public function testHttpClientUnsupportedScheme(): void
    {
        $client = new HttpClient(new class () implements ConnectionFactoryInterface {
            #[\Override]
            public function connect(
                string $protocolId,
                string $endpoint,
                ?ConnectionOptions $options = null,
            ): ConnectionInterface {
                unset($protocolId, $endpoint, $options);

                return new BufferConnection("HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n");
            }
        });
        $this->expectException(ConnectionException::class);
        $client->get('ftp://example.com/file');
    }

    /**
     * Редиректы: relative, absolute, пустой Location.
     */
    public function testHttpClientRedirects(): void
    {
        $relativeFactory = new class () implements ConnectionFactoryInterface {
            public int $n = 0;

            #[\Override]
            public function connect(
                string $protocolId,
                string $endpoint,
                ?ConnectionOptions $options = null,
            ): ConnectionInterface {
                unset($protocolId, $endpoint, $options);
                ++$this->n;

                if ($this->n === 1) {
                    return new BufferConnection(
                        "HTTP/1.1 302 Found\r\nLocation: next\r\nContent-Length: 0\r\n\r\n",
                    );
                }

                return new BufferConnection("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nok");
            }
        };
        self::assertSame('ok', (new HttpClient($relativeFactory))->get('http://example.com:8080/base/page')->body());

        $emptyLocation = new class () implements ConnectionFactoryInterface {
            #[\Override]
            public function connect(
                string $protocolId,
                string $endpoint,
                ?ConnectionOptions $options = null,
            ): ConnectionInterface {
                unset($protocolId, $endpoint, $options);

                return new BufferConnection(
                    "HTTP/1.1 302 Found\r\nLocation: \r\nContent-Length: 0\r\n\r\n",
                );
            }
        };
        self::assertSame(302, (new HttpClient($emptyLocation))->get('http://example.com/')->statusCode());

        $absoluteFactory = new class () implements ConnectionFactoryInterface {
            public int $n = 0;

            #[\Override]
            public function connect(
                string $protocolId,
                string $endpoint,
                ?ConnectionOptions $options = null,
            ): ConnectionInterface {
                unset($protocolId, $endpoint, $options);
                ++$this->n;

                if ($this->n === 1) {
                    return new BufferConnection(
                        "HTTP/1.1 301 Moved\r\nLocation: http://other.test/done\r\nContent-Length: 0\r\n\r\n",
                    );
                }

                return new BufferConnection("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nok");
            }
        };
        self::assertSame('ok', (new HttpClient($absoluteFactory))->get('http://example.com/start')->body());

        $relativeSlash = new class () implements ConnectionFactoryInterface {
            public int $n = 0;

            #[\Override]
            public function connect(
                string $protocolId,
                string $endpoint,
                ?ConnectionOptions $options = null,
            ): ConnectionInterface {
                unset($protocolId, $endpoint, $options);
                ++$this->n;

                if ($this->n === 1) {
                    return new BufferConnection(
                        "HTTP/1.1 302 Found\r\nLocation: /abs\r\nContent-Length: 0\r\n\r\n",
                    );
                }

                return new BufferConnection("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nok");
            }
        };
        self::assertSame('ok', (new HttpClient($relativeSlash))->get('http://example.com/folder/page')->body());
    }

    /**
     * HttpClient: URL без path (нормализация в /).
     */
    public function testHttpClientDefaultPath(): void
    {
        $factory = new class () implements ConnectionFactoryInterface {
            #[\Override]
            public function connect(
                string $protocolId,
                string $endpoint,
                ?ConnectionOptions $options = null,
            ): ConnectionInterface {
                unset($protocolId, $endpoint, $options);

                return new BufferConnection("HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n");
            }
        };
        self::assertSame(200, (new HttpClient($factory))->get('http://example.com?only=query')->statusCode());
    }

    /**
     * HttpResponse: разделитель, json error, malformed header line.
     */
    public function testHttpResponseMissingSeparator(): void
    {
        $this->expectException(ConnectionException::class);
        HttpResponse::fromRaw("HTTP/1.1 200 OK\r\nOnly-Header-No-Body");
    }

    /**
     * HttpResponse: строка заголовка без двоеточия и JSON error.
     */
    public function testHttpResponseMalformedHeaderAndJson(): void
    {
        $raw = "HTTP/1.1 200 OK\r\nMalformed-Header-Line\r\nContent-Length: 5\r\n\r\nhello";
        self::assertSame('hello', HttpResponse::fromRaw($raw)->body());

        $badJson = HttpResponse::fromRaw("HTTP/1.1 200 OK\r\nContent-Length: 3\r\n\r\n{{");
        $this->expectException(ConnectionException::class);
        $badJson->json();
    }

    /**
     * SshBinary edge cases.
     */
    public function testSshBinaryEdges(): void
    {
        self::assertStringStartsWith("\x00", SshBinary::mpint("\x80\x01"));

        $buffer = SshBinary::uint32(4) . 'data';
        $offset = 0;
        self::assertSame('data', SshBinary::readString($buffer, $offset));

        $short = SshBinary::uint32(99);
        $offsetShort = 0;
        self::assertSame('', SshBinary::readString($short, $offsetShort));

        $mpintRaw = SshBinary::sshString("\x00\x01\x02");
        $offsetMp = 0;
        self::assertSame("\x01\x02", SshBinary::readMpint($mpintRaw, $offsetMp));
        self::assertNotSame('', SshBinary::nameList(['ssh-rsa', 'ssh-ed25519']));

        $emptyMp = SshBinary::sshString('');
        $offsetEmpty = 0;
        self::assertSame('', SshBinary::readMpint($emptyMp, $offsetEmpty));
    }

    /**
     * SshConnection API и SFTP error paths.
     */
    public function testSshAndSftpErrorPaths(): void
    {
        $session = new FakeSshSession('out', []);
        $connection = new SshConnection($session, 'ssh', 'remote:22', ['k' => 'v']);

        self::assertSame(7, $connection->osiLayer());
        self::assertSame('remote:22', $connection->remoteEndpoint());
        self::assertSame(['k' => 'v'], $connection->metadata());
        self::assertSame($session, $connection->resource());
        $session->write('z');
        self::assertSame(['z'], iterator_to_array($connection->readChunks(1)));

        $existsFail = new FakeSshSession('x', []);
        self::assertFalse($existsFail->openSftp()->exists('/missing'));

        $statusOk = chr(101)
            . SshBinary::uint32(1)
            . SshBinary::uint32(0)
            . SshBinary::sshString('')
            . SshBinary::sshString('');
        $badOpen = new FakeSshSession('x', [$statusOk]);

        try {
            $badOpen->openSftp()->get('/bad');
            self::fail('ожидалось исключение OPEN');
        } catch (ConnectionException) {
        }

        $handle = 'H';
        $openOk = chr(102) . SshBinary::uint32(1) . SshBinary::sshString($handle);
        $emptyRead = chr(101)
            . SshBinary::uint32(2)
            . SshBinary::uint32(1)
            . SshBinary::sshString('')
            . SshBinary::sshString('');
        $closeOk = chr(101)
            . SshBinary::uint32(3)
            . SshBinary::uint32(0)
            . SshBinary::sshString('')
            . SshBinary::sshString('');
        $sessionRead = new FakeSshSession('x', [$openOk, $emptyRead, $closeOk]);
        self::assertSame('', $sessionRead->openSftp()->get('/empty'));

        $wrongType = chr(102) . SshBinary::uint32(1) . SshBinary::sshString('D');
        $badDir = chr(99) . SshBinary::uint32(2);
        $dirClose = chr(101)
            . SshBinary::uint32(3)
            . SshBinary::uint32(0)
            . SshBinary::sshString('')
            . SshBinary::sshString('');
        $listFail = new FakeSshSession('x', [$wrongType, $badDir, $dirClose]);

        try {
            $listFail->openSftp()->list('/dir');
            self::fail('ожидалось исключение READDIR');
        } catch (ConnectionException) {
        }

        $openDirFail = chr(101)
            . SshBinary::uint32(1)
            . SshBinary::uint32(1)
            . SshBinary::sshString('')
            . SshBinary::sshString('');

        try {
            (new FakeSshSession('x', [$openDirFail]))->openSftp()->list('/z');
            self::fail('ожидалось исключение OPENDIR');
        } catch (ConnectionException) {
        }

        $openH = chr(102) . SshBinary::uint32(1) . SshBinary::sshString('h');
        $badRead = chr(99) . SshBinary::uint32(2);
        $closeH = chr(101)
            . SshBinary::uint32(3)
            . SshBinary::uint32(0)
            . SshBinary::sshString('')
            . SshBinary::sshString('');

        try {
            (new FakeSshSession('x', [$openH, $badRead, $closeH]))->openSftp()->get('/r');
            self::fail('ожидалось исключение READ');
        } catch (ConnectionException) {
        }

        $badStatus = chr(99) . SshBinary::uint32(1);

        try {
            (new FakeSshSession('x', [$openH, $badStatus]))->openSftp()->put('/p', 'x');
            self::fail('ожидалось исключение STATUS');
        } catch (ConnectionException) {
        }

        $openDirOk = chr(102) . SshBinary::uint32(1) . SshBinary::sshString('dh');
        $emptyDir = '';
        $closeDir = chr(101)
            . SshBinary::uint32(3)
            . SshBinary::uint32(0)
            . SshBinary::sshString('')
            . SshBinary::sshString('');
        self::assertSame(
            [],
            (new FakeSshSession('x', [$openDirOk, $emptyDir, $closeDir]))->openSftp()->list('/e'),
        );

        $openRead = chr(102) . SshBinary::uint32(1) . SshBinary::sshString('rh');
        $emptyReadPacket = '';
        $closeRead = chr(101)
            . SshBinary::uint32(3)
            . SshBinary::uint32(0)
            . SshBinary::sshString('')
            . SshBinary::sshString('');
        self::assertSame(
            '',
            (new FakeSshSession('x', [$openRead, $emptyReadPacket, $closeRead]))->openSftp()->get('/empty2'),
        );

        $openPut = chr(102) . SshBinary::uint32(1) . SshBinary::sshString('ph');
        $writeOk = chr(101)
            . SshBinary::uint32(2)
            . SshBinary::uint32(0)
            . SshBinary::sshString('')
            . SshBinary::sshString('');
        $badClose = chr(101)
            . SshBinary::uint32(99)
            . SshBinary::uint32(0)
            . SshBinary::sshString('')
            . SshBinary::sshString('');

        try {
            (new FakeSshSession('x', [$openPut, $writeOk, $badClose]))->openSftp()->put('/p', 'z');
            self::fail('ожидалось исключение status id');
        } catch (ConnectionException $exception) {
            self::assertStringContainsString('status error', $exception->getMessage());
        }
    }

    /**
     * SshPublicKeyAuth error paths и rsa-sha2-512.
     */
    public function testSshPublicKeyAuthEmptyKey(): void
    {
        $this->expectException(ConnectionException::class);
        (new SshPublicKeyAuth())->load(AuthCredentials::withPublicKey('u', ''));
    }

    /**
     * Битый PEM и rsa-sha2-512 подпись.
     */
    public function testSshPublicKeyAuthInvalidPemAndSha512(): void
    {
        $helper = new SshPublicKeyAuth();

        try {
            $helper->load(AuthCredentials::withPublicKey('u', 'not-a-pem'));
            self::fail('ожидалось исключение');
        } catch (ConnectionException) {
        }

        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        self::assertNotFalse($key);
        $pemBuffer = '';
        self::assertTrue(openssl_pkey_export($key, $pemBuffer));
        $pem = $this->requireExportedPem($pemBuffer);
        $parts = $helper->load(AuthCredentials::withPublicKey('u', $pem));
        $parts['algorithm'] = 'rsa-sha2-512';
        $request = $helper->buildRequest(str_repeat("\x03", 32), 'u', $parts);
        self::assertStringContainsString('rsa-sha2-512', $request);

        $path = tempnam(sys_get_temp_dir(), 'ccm-key');
        self::assertNotFalse($path);
        file_put_contents($path, $pem);
        $fromFile = $helper->load(AuthCredentials::withPublicKey('u', $path));
        self::assertSame('rsa-sha2-256', $fromFile['algorithm']);
        unlink($path);

        if (defined('OPENSSL_KEYTYPE_EC')) {
            $ec = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
            if ($ec !== false) {
                $ecPemBuffer = '';
                self::assertTrue(openssl_pkey_export($ec, $ecPemBuffer));
                $ecPem = $this->requireExportedPem($ecPemBuffer);

                try {
                    $helper->load(AuthCredentials::withPublicKey('u', $ecPem));
                    self::fail('ожидалось исключение для EC');
                } catch (ConnectionException) {
                }
            }
        }
    }

    /**
     * StreamOpener async connect и completeAsyncConnect ошибки.
     */
    public function testStreamOpenerAsync(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($server);
        $name = stream_socket_get_name($server, false);
        self::assertIsString($name);
        $opener = new StreamOpener();
        $async = new FiberAsyncRuntime();
        $stream = $opener->open(
            'tcp://' . $name,
            ConnectionOptions::defaults()->allowPrivateAddresses()->withMode('async'),
            $async,
        );
        fclose($stream);
        fclose($server);

        $complete = new ReflectionMethod(StreamOpener::class, 'completeAsyncConnect');
        $this->expectException(ConnectionException::class);
        $complete->invoke($opener, null, 'tcp://x', ConnectionOptions::defaults(), $async);
    }

    /**
     * FiberAsyncRuntime splitTimeout: <=0 и rollover микросекунд.
     */
    public function testFiberSplitTimeout(): void
    {
        $method = new ReflectionMethod(FiberAsyncRuntime::class, 'splitTimeout');
        $runtime = new FiberAsyncRuntime();

        self::assertSame([0, 0], $method->invoke($runtime, 0.0));
        self::assertSame([2, 0], $method->invoke($runtime, 1.9999999999999998));
    }

    /**
     * ConnectManager: sftp при неверном типе соединения.
     */
    public function testConnectManagerSftpWrongType(): void
    {
        $badFactory = new class () implements ConnectionFactoryInterface {
            #[\Override]
            public function connect(
                string $protocolId,
                string $endpoint,
                ?ConnectionOptions $options = null,
            ): ConnectionInterface {
                unset($protocolId, $endpoint, $options);

                return new LogicalConnection('sftp', 7, 'x');
            }
        };
        $this->expectException(ConnectionException::class);
        (new ConnectManager(factory: $badFactory))->sftp(
            '127.0.0.1:22',
            AuthCredentials::withPassword('u', 'p'),
            ConnectionOptions::defaults()->allowPrivateAddresses(),
        );
    }

    /**
     * EndpointGuard: assertConnectedPeer блокирует localhost.
     */
    public function testEndpointGuardAssertConnectedPeerBlocks(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($server);
        $client = stream_socket_client((string) stream_socket_get_name($server, false));
        self::assertNotFalse($client);
        $guard = new EndpointGuard(new FixedDnsResolver());

        try {
            $guard->assertConnectedPeer($client, ConnectionOptions::defaults());
            self::fail('ожидался SecurityException');
        } catch (SecurityException) {
        } finally {
            fclose($client);
            fclose($server);
        }
    }

    /**
     * EndpointGuard: pin URL с query/path.
     */
    public function testEndpointGuardReplaceHostInUrl(): void
    {
        $guard = new EndpointGuard(new FixedDnsResolver(['cdn.example' => ['8.8.8.8']]));
        $pinned = $guard->prepare('https://cdn.example:8443/assets?q=1', ConnectionOptions::defaults());
        self::assertSame('https://8.8.8.8:8443/assets?q=1', $pinned->connectEndpoint());
        self::assertSame(['8.8.8.8'], $pinned->resolvedIps());

        $bracket = $guard->prepare('[2001:db8::5]:22', ConnectionOptions::defaults()->allowPrivateAddresses());
        self::assertStringContainsString('2001:db8::5', $bracket->connectEndpoint());

        $brokenBracket = $guard->prepare('[broken:22', ConnectionOptions::defaults()->allowPrivateAddresses());
        self::assertSame('[broken:22', $brokenBracket->connectEndpoint());
    }

    /**
     * assertConnectedPeer: не-resource и пустой peer.
     */
    public function testEndpointGuardAssertConnectedPeerNoops(): void
    {
        $this->expectNotToPerformAssertions();
        $guard = new EndpointGuard(new FixedDnsResolver());
        $guard->assertConnectedPeer(null, ConnectionOptions::defaults());
        $guard->assertConnectedPeer(null, ConnectionOptions::defaults()->allowPrivateAddresses());
    }

    /**
     * PhpDnsResolver: AAAA из публичного имени.
     */
    public function testPhpDnsResolverAaaaRecords(): void
    {
        $ips = (new \CloudCastle\ConnectManager\Security\PhpDnsResolver())->resolve('one.one.one.one');
        self::assertNotSame([], $ips);
    }

    /**
     * @param mixed $exported Результат openssl_pkey_export by-ref
     */
    private function requireExportedPem(mixed $exported): string
    {
        self::assertIsString($exported);
        self::assertNotSame('', $exported);

        return $exported;
    }
}

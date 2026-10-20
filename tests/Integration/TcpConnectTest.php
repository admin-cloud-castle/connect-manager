<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Integration;

use CloudCastle\ConnectManager\Connection\StreamConnection;
use CloudCastle\ConnectManager\ConnectManager;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Интеграционные TCP/Unix/async сценарии.
 */
#[CoversClass(ConnectManager::class)]
#[CoversClass(StreamConnection::class)]
#[CoversClass(\CloudCastle\ConnectManager\ConnectManager::class)]
#[CoversClass(\CloudCastle\ConnectManager\Async\FiberAsyncRuntime::class)]
#[CoversClass(\CloudCastle\ConnectManager\Async\NativeStreamSelector::class)]
#[CoversClass(\CloudCastle\ConnectManager\Contract\ConnectionOptions::class)]
#[CoversClass(\CloudCastle\ConnectManager\Connection\LogicalConnection::class)]
#[CoversClass(\CloudCastle\ConnectManager\Connection\StreamConnection::class)]
#[CoversClass(\CloudCastle\ConnectManager\Driver\ConnectionFactory::class)]
#[CoversClass(\CloudCastle\ConnectManager\Driver\StreamOpener::class)]
#[CoversClass(\CloudCastle\ConnectManager\Driver\TransportResolver::class)]
#[CoversClass(\CloudCastle\ConnectManager\Pool\ConnectionPool::class)]
#[CoversClass(\CloudCastle\ConnectManager\Protocol\ProtocolCatalog::class)]
#[CoversClass(\CloudCastle\ConnectManager\Protocol\ProtocolDefinition::class)]
#[CoversClass(\CloudCastle\ConnectManager\Protocol\ProtocolRegistry::class)]
#[CoversClass(\CloudCastle\ConnectManager\Security\EndpointGuard::class)]
#[CoversClass(\CloudCastle\ConnectManager\Exception\ConnectManagerException::class)]
#[CoversClass(\CloudCastle\ConnectManager\Exception\ConnectionException::class)]
#[CoversClass(\CloudCastle\ConnectManager\Exception\ProtocolException::class)]
#[CoversClass(\CloudCastle\ConnectManager\Exception\SecurityException::class)]
#[CoversClass(\CloudCastle\ConnectManager\Exception\TimeoutException::class)]
/**
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.ShortVariable")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.MissingImport")
 * @SuppressWarnings("PHPMD.ExcessivePublicCount")
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 * @SuppressWarnings("PHPMD.ExcessiveClassLength")
 * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
 * @SuppressWarnings("PHPMD.CamelCaseMethodName")
 * @SuppressWarnings("PHPMD.CamelCaseParameterName")
 * @SuppressWarnings("PHPMD.CamelCaseVariableName")
 * @SuppressWarnings("PHPMD.UnusedLocalVariable")
 * @SuppressWarnings("PHPMD.UnusedFormalParameter")
 * @SuppressWarnings("PHPMD.ErrorControlOperator")
 */
final class TcpConnectTest extends TestCase
{
    /**
     * TCP connect к локальному echo-серверу.
     */
    public function testTcpEchoRoundtrip(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        self::assertTrue(is_resource($server), 'bind failed');
        $name = stream_socket_get_name($server, false);
        self::assertIsString($name);

        $manager = new ConnectManager();
        $options = ConnectionOptions::defaults()->allowPrivateAddresses();
        $client = $manager->connect('tcp', $name, $options);
        self::assertInstanceOf(StreamConnection::class, $client);

        $peer = @stream_socket_accept($server, 1.0);
        self::assertNotFalse($peer);

        $client->write("ping\n");
        $buf = fread($peer, 16);
        self::assertSame("ping\n", $buf);
        fwrite($peer, "pong\n");
        self::assertSame("pong\n", $client->read(16));

        $client->close();
        fclose($peer);
        fclose($server);
    }

    /**
     * socket-протокол и http (tcp-транспорт) к локальному серверу.
     */
    public function testSocketAndHttpProtocolIds(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        self::assertNotFalse($server);
        $name = stream_socket_get_name($server, false);
        self::assertIsString($name);
        // host:port без схемы
        $parts = explode(':', $name);
        $endpoint = $parts[0] . ':' . $parts[1];

        $manager = new ConnectManager();
        $options = ConnectionOptions::defaults()->allowPrivateAddresses();
        $conn = $manager->connect('socket', $endpoint, $options);
        $peer = @stream_socket_accept($server, 1.0);
        self::assertNotFalse($peer);
        self::assertSame('socket', $conn->protocol());
        $conn->close();
        fclose($peer);
        fclose($server);
    }

    /**
     * connectAsync выполняет callback.
     */
    public function testConnectAsync(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        self::assertNotFalse($server);
        $name = stream_socket_get_name($server, false);
        self::assertIsString($name);

        $manager = new ConnectManager();
        $options = ConnectionOptions::defaults()->allowPrivateAddresses();

        $result = $manager->connectAsync('tcp', $name, static function ($connection): string {
            $connection->write('a');

            return $connection->protocol();
        }, $options);

        $peer = @stream_socket_accept($server, 1.0);

        if (is_resource($peer)) {
            fclose($peer);
        }

        fclose($server);
        self::assertSame('tcp', $result);
    }

    /**
     * Unix domain socket.
     */
    public function testUnixSocket(): void
    {
        $dir = sys_get_temp_dir();
        $path = $dir . DIRECTORY_SEPARATOR . 'cc-cm-' . bin2hex(random_bytes(4)) . '.sock';
        self::assertStringStartsWith($dir, $path);

        if (is_file($path)) {
            self::assertTrue(unlink($path));
        }

        $server = stream_socket_server('unix://' . $path, $errno, $errstr);
        self::assertTrue(is_resource($server), 'bind failed');

        $manager = new ConnectManager();
        $options = ConnectionOptions::defaults()->allowPrivateAddresses();
        $client = $manager->connect('unix', $path, $options);
        $peer = @stream_socket_accept($server, 1.0);
        self::assertNotFalse($peer);
        $client->write('u');
        self::assertSame('u', fread($peer, 1));
        $client->close();
        fclose($peer);
        fclose($server);

        if (is_file($path)) {
            self::assertTrue(unlink($path));
        }
    }
}

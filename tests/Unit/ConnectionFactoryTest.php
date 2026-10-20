<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\Connection\LogicalConnection;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Driver\ConnectionFactory;
use CloudCastle\ConnectManager\Driver\StreamOpener;
use CloudCastle\ConnectManager\Driver\TransportResolver;
use CloudCastle\ConnectManager\Exception\ConnectionException;
use CloudCastle\ConnectManager\Exception\TimeoutException;
use CloudCastle\ConnectManager\Protocol\ProtocolRegistry;
use CloudCastle\ConnectManager\Security\EndpointGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Тесты фабрики и opener.
 */
#[CoversClass(ConnectionFactory::class)]
#[CoversClass(StreamOpener::class)]
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
#[UsesClass(\CloudCastle\ConnectManager\Driver\Ssh\SshConnector::class)]
#[UsesClass(\CloudCastle\ConnectManager\Security\PinnedEndpoint::class)]
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
final class ConnectionFactoryTest extends TestCase
{
    /**
     * Недоступный порт → ConnectionException.
     */
    public function testConnectFailure(): void
    {
        $factory = new ConnectionFactory(new ProtocolRegistry());
        $options = new ConnectionOptions(0.2, 0.2, true, false);

        $this->expectException(ConnectionException::class);
        $factory->connect('tcp', '127.0.0.1:1', $options);
    }

    /**
     * Логическое соединение для adsl.
     */
    public function testLogicalForUnsupported(): void
    {
        $factory = new ConnectionFactory(
            new ProtocolRegistry(),
            new EndpointGuard(),
            new TransportResolver(),
            new StreamOpener(),
        );
        $conn = $factory->connect('adsl', 'x', ConnectionOptions::defaults()->allowPrivateAddresses());
        self::assertInstanceOf(LogicalConnection::class, $conn);
        self::assertFalse($conn->metadata()['native']);
        $conn->close();
    }

    /**
     * Opener к недоступному адресу бросает Connection/TimeoutException.
     */
    public function testOpenerTimeout(): void
    {
        $opener = new StreamOpener();
        $options = new ConnectionOptions(0.15, 0.15, true, false);

        try {
            $opener->open('tcp://127.0.0.1:1', $options);
            self::fail('ожидалось исключение соединения');
        } catch (TimeoutException | ConnectionException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }
    }
}

<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\Connection\LogicalConnection;
use CloudCastle\ConnectManager\Exception\ConnectionException;
use CloudCastle\ConnectManager\Tests\Support\MixedValues;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Тесты LogicalConnection.
 */
#[CoversClass(LogicalConnection::class)]
#[CoversClass(\CloudCastle\ConnectManager\ConnectManager::class)]
#[CoversClass(\CloudCastle\ConnectManager\Async\FiberAsyncRuntime::class)]
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
 */
final class LogicalConnectionTest extends TestCase
{
    /**
     * I/O после attach.
     */
    public function testAttachEnablesIo(): void
    {
        $connection = new LogicalConnection('adsl', 1, 'line', ['a' => 1]);
        self::assertTrue($connection->metadata()['logical']);

        $this->expectException(ConnectionException::class);
        $connection->write('x');
    }

    /**
     * Полный цикл attach → write/read/chunks → close.
     */
    public function testFullAttachedLifecycle(): void
    {
        $connection = new LogicalConnection('ethernet', 2, 'nic0');
        $stream = fopen('php://temp', 'r+b');
        self::assertNotFalse($stream);
        $connection->attach($stream);

        self::assertSame(4, $connection->write('ping'));
        ftruncate($stream, 0);
        rewind($stream);
        fwrite($stream, '0123456789');
        rewind($stream);

        self::assertSame('0123', $connection->read(4));
        $chunks = iterator_to_array($connection->readChunks(3));
        self::assertNotSame([], $chunks);
        self::assertSame('ethernet', $connection->protocol());
        self::assertSame(2, $connection->osiLayer());
        self::assertSame('nic0', $connection->remoteEndpoint());
        self::assertNotNull($connection->resource());

        $connection->close();
        self::assertFalse($connection->isOpen());
    }

    /**
     * Невалидный attach.
     */
    public function testAttachRejectsNonResource(): void
    {
        $connection = new LogicalConnection('can', 0, null);

        $this->expectException(ConnectionException::class);
        $connection->attach(MixedValues::notAStream());
    }

    /**
     * Невалидные параметры I/O.
     */
    public function testInvalidIoParams(): void
    {
        $connection = new LogicalConnection('can', 0, null);
        $stream = fopen('php://temp', 'r+b');
        self::assertNotFalse($stream);
        $connection->attach($stream);

        try {
            $connection->read(0);
            self::fail('expected read(0)');
        } catch (ConnectionException $exception) {
            self::assertStringContainsString('≥ 1', $exception->getMessage());
        }

        try {
            iterator_to_array($connection->readChunks(0));
            self::fail('expected chunks');
        } catch (ConnectionException $exception) {
            self::assertStringContainsString('чанка', $exception->getMessage());
        }

        $connection->close();

        $this->expectException(ConnectionException::class);
        $connection->read(1);
    }
}

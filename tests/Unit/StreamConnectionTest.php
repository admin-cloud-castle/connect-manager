<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\Connection\StreamConnection;
use CloudCastle\ConnectManager\Exception\ConnectionException;
use CloudCastle\ConnectManager\Tests\Support\MixedValues;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Тесты StreamConnection.
 */
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
 */
final class StreamConnectionTest extends TestCase
{
    /**
     * Запись/чтение через php://temp.
     */
    public function testWriteReadAndChunks(): void
    {
        $stream = fopen('php://temp', 'r+b');
        self::assertNotFalse($stream);

        $connection = new StreamConnection($stream, 'tcp', 4, '127.0.0.1:9', ['k' => 'v']);
        self::assertTrue($connection->isOpen());
        self::assertSame('tcp', $connection->protocol());
        self::assertSame(4, $connection->osiLayer());
        self::assertSame('127.0.0.1:9', $connection->remoteEndpoint());
        self::assertSame(5, $connection->write('hello'));

        rewind($stream);
        self::assertSame('hello', $connection->read(5));

        fwrite($stream, 'world-data');
        rewind($stream);
        // после read позиция не в начале — перезапишем
        ftruncate($stream, 0);
        rewind($stream);
        fwrite($stream, 'abcdefghij');
        rewind($stream);

        $chunks = iterator_to_array($connection->readChunks(3));
        self::assertSame(['abc', 'def', 'ghi', 'j'], $chunks);

        $meta = $connection->metadata();
        self::assertSame('v', $meta['k']);
        self::assertArrayHasKey('stream', $meta);
        self::assertNotNull($connection->resource());

        $connection->close();
        self::assertFalse($connection->isOpen());
    }

    /**
     * Неверный ресурс.
     */
    public function testInvalidResourceThrows(): void
    {
        $this->expectException(ConnectionException::class);

        new StreamConnection(MixedValues::notAStream(), 'tcp', 4, null);
    }

    /**
     * Операции на закрытом соединении.
     */
    public function testClosedThrows(): void
    {
        $stream = fopen('php://temp', 'r+b');
        self::assertNotFalse($stream);
        $connection = new StreamConnection($stream, 'tcp', 4, null);
        $connection->close();

        $this->expectException(ConnectionException::class);
        $connection->write('x');
    }

    /**
     * Некорректная длина чтения.
     */
    public function testInvalidReadLength(): void
    {
        $stream = fopen('php://temp', 'r+b');
        self::assertNotFalse($stream);
        $connection = new StreamConnection($stream, 'tcp', 4, null);

        $this->expectException(ConnectionException::class);
        $connection->read(0);
    }

    /**
     * Некорректный chunk size.
     */
    public function testInvalidChunkSize(): void
    {
        $stream = fopen('php://temp', 'r+b');
        self::assertNotFalse($stream);
        $connection = new StreamConnection($stream, 'tcp', 4, null);

        $this->expectException(ConnectionException::class);
        iterator_to_array($connection->readChunks(0));
    }
}

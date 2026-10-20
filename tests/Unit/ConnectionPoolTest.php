<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\Connection\LogicalConnection;
use CloudCastle\ConnectManager\Contract\ConnectionFactoryInterface;
use CloudCastle\ConnectManager\Contract\ConnectionInterface;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Pool\ConnectionPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Тесты пула соединений.
 */
#[CoversClass(ConnectionPool::class)]
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
final class ConnectionPoolTest extends TestCase
{
    /**
     * Acquire создаёт, release возвращает, повторный acquire переиспользует.
     */
    public function testAcquireReleaseReuse(): void
    {
        $created = 0;
        $factory = new class ($created) implements ConnectionFactoryInterface {
            private int $created;

            public function __construct(int &$created)
            {
                $this->created = &$created;
            }

            #[\Override]
            public function connect(
                string $protocolId,
                string $endpoint,
                ?ConnectionOptions $options = null,
            ): ConnectionInterface {
                ++$this->created;

                return new LogicalConnection($protocolId, 4, $endpoint);
            }
        };

        $pool = new ConnectionPool($factory, 2);
        $a = $pool->acquire('tcp', 'example.com:80');
        self::assertGreaterThan(0, $created);
        $afterFirst = $created;
        $pool->release($a);
        self::assertSame(1, $pool->size());

        $b = $pool->acquire('tcp', 'example.com:80');
        self::assertSame($afterFirst, $created);
        self::assertSame($a, $b);

        $pool->release($b);
        $pool->drain();
        self::assertSame(0, $pool->size());
    }

    /**
     * Переполнение maxPerKey закрывает лишнее.
     */
    public function testMaxPerKeyClosesExtra(): void
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

        $pool = new ConnectionPool($factory, 1);
        $a = $pool->acquire('tcp', 'h:1');
        $b = $pool->acquire('tcp', 'h:1');
        $pool->release($a);
        $pool->release($b);
        self::assertSame(1, $pool->size());
        self::assertFalse($b->isOpen());
        $pool->drain();
    }

    /**
     * Закрытое соединение при release игнорируется; сломанное idle пересоздаётся.
     */
    public function testClosedOnReleaseAndStaleIdle(): void
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

        $pool = new ConnectionPool($factory, 2);
        $c = $pool->acquire('udp', 'h:2');
        $c->close();
        $pool->release($c);
        self::assertSame(0, $pool->size());

        $open = $pool->acquire('udp', 'h:2');
        $pool->release($open);
        // испортим idle вручную
        $open->close();
        $fresh = $pool->acquire('udp', 'h:2');
        self::assertTrue($fresh->isOpen());
        $fresh->close();
        $pool->drain();
    }
}

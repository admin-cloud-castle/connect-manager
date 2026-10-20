<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Load;

use CloudCastle\ConnectManager\Connection\LogicalConnection;
use CloudCastle\ConnectManager\Contract\ConnectionFactoryInterface;
use CloudCastle\ConnectManager\Contract\ConnectionInterface;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Pool\ConnectionPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Нагрузочный сценарий пула.
 */
#[CoversClass(ConnectionPool::class)]
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
 * @SuppressWarnings("PHPMD.CamelCaseMethodName")
 * @SuppressWarnings("PHPMD.CamelCaseParameterName")
 * @SuppressWarnings("PHPMD.CamelCaseVariableName")
 * @SuppressWarnings("PHPMD.UnusedLocalVariable")
 * @SuppressWarnings("PHPMD.UnusedFormalParameter")
 * @SuppressWarnings("PHPMD.ErrorControlOperator")
 */
final class PoolLoadTest extends TestCase
{
    /**
     * Тысячи acquire/release без роста idle сверх max.
     */
    public function testThousandAcquireRelease(): void
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

        $pool = new ConnectionPool($factory, 4);

        for ($i = 0; $i < 1000; ++$i) {
            $c = $pool->acquire('tcp', 'load:1');
            $pool->release($c);
        }

        self::assertLessThanOrEqual(4, $pool->size());
        $pool->drain();
        self::assertSame(0, $pool->size());
    }
}

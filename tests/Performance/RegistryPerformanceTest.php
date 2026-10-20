<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Performance;

use CloudCastle\ConnectManager\ConnectManager;
use CloudCastle\ConnectManager\Protocol\ProtocolRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Регрессионные замеры производительности реестра.
 */
#[CoversClass(ProtocolRegistry::class)]
#[CoversClass(ConnectManager::class)]
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
#[UsesClass(\CloudCastle\ConnectManager\Driver\Http\HttpClient::class)]
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
final class RegistryPerformanceTest extends TestCase
{
    /**
     * Lookup 50k раз должен укладываться в бюджет.
     */
    public function testRegistryLookupBudget(): void
    {
        $registry = new ProtocolRegistry();
        $iterations = 50000;
        $start = hrtime(true);

        for ($i = 0; $i < $iterations; ++$i) {
            $registry->get('tcp');
        }

        $elapsedMs = ((float) (hrtime(true) - $start)) / 1e6;
        // щедрый бюджет для CI; локально обычно << 100ms
        self::assertLessThan(2000.0, $elapsedMs, sprintf('lookup слишком медленный: %.2f ms', $elapsedMs));
    }

    /**
     * Создание менеджера и полный обход генератора.
     */
    public function testManagerBootstrapBudget(): void
    {
        $start = hrtime(true);
        $manager = new ConnectManager();
        $n = 0;

        foreach ($manager->protocols()->all() as $definition) {
            ++$n;
            unset($definition);
        }

        $elapsedMs = ((float) (hrtime(true) - $start)) / 1e6;
        self::assertGreaterThan(100, $n);
        self::assertLessThan(1000.0, $elapsedMs);
    }
}

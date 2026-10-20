<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\Connection\LogicalConnection;
use CloudCastle\ConnectManager\ConnectManager;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Exception\ConnectManagerException;
use CloudCastle\ConnectManager\Exception\SecurityException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Модульные тесты фасада ConnectManager.
 */
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
final class ConnectManagerTest extends TestCase
{
    /**
     * Проверяет composer-имя пакета.
     */
    public function testNameReturnsComposerPackageName(): void
    {
        $package = new ConnectManager();

        self::assertSame('cloud-castle/connect-manager', $package->name());
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', ConnectManager::VERSION);
    }

    /**
     * Проверяет abort().
     */
    public function testAbortThrowsConnectManagerException(): void
    {
        $package = new ConnectManager();

        $this->expectException(ConnectManagerException::class);
        $this->expectExceptionMessage('тест');

        $package->abort('тест');
    }

    /**
     * Реестр содержит ≥100 протоколов из каталога OSI+.
     */
    public function testProtocolsRegistryIsPopulated(): void
    {
        $manager = new ConnectManager();
        $registry = $manager->protocols();

        self::assertGreaterThanOrEqual(150, $registry->count());
        self::assertTrue($registry->has('tcp'));
        self::assertTrue($registry->has('socket'));
        self::assertTrue($registry->has('http'));
        self::assertSame('Transmission Control Protocol', $registry->get('tcp')->name());
    }

    /**
     * Логическое соединение для L1-протокола без нативного транспорта.
     */
    public function testConnectReturnsLogicalForPhysicalProtocol(): void
    {
        $manager = new ConnectManager();
        $connection = $manager->connect('adsl', 'line-1', ConnectionOptions::defaults()->allowPrivateAddresses());

        self::assertInstanceOf(LogicalConnection::class, $connection);
        self::assertSame('adsl', $connection->protocol());
        self::assertSame(1, $connection->osiLayer());
        self::assertTrue($connection->isOpen());
        $connection->close();
        self::assertFalse($connection->isOpen());
    }

    /**
     * SSRF-guard блокирует localhost по умолчанию.
     */
    public function testConnectBlocksLocalhostByDefault(): void
    {
        $manager = new ConnectManager();

        $this->expectException(SecurityException::class);

        $manager->connect('tcp', '127.0.0.1:9');
    }

    /**
     * Пул и async доступны с фасада.
     */
    public function testPoolAndAsyncAccessors(): void
    {
        $manager = new ConnectManager();

        self::assertSame(0, $manager->pool()->size());
        self::assertSame(7, $manager->async()->run(static fn (): int => 7));
    }

    /**
     * Перечисление протоколов через генератор.
     */
    public function testProtocolsGeneratorYieldsAll(): void
    {
        $manager = new ConnectManager();
        $count = 0;

        foreach ($manager->protocols()->all() as $id => $definition) {
            self::assertSame($id, $definition->id());
            ++$count;
        }

        self::assertSame($count, $manager->protocols()->count());
    }
}

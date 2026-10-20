<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\Exception\ProtocolException;
use CloudCastle\ConnectManager\Protocol\ProtocolCatalog;
use CloudCastle\ConnectManager\Protocol\ProtocolDefinition;
use CloudCastle\ConnectManager\Protocol\ProtocolRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Тесты реестра и каталога протоколов.
 */
#[CoversClass(ProtocolRegistry::class)]
#[CoversClass(ProtocolCatalog::class)]
#[CoversClass(ProtocolDefinition::class)]
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
final class ProtocolRegistryTest extends TestCase
{
    /**
     * Каталог и реестр согласованы по размеру.
     */
    public function testCatalogSizeMatchesRegistry(): void
    {
        $registry = new ProtocolRegistry();

        self::assertSame(ProtocolCatalog::size(), $registry->count());
    }

    /**
     * Регистрация пользовательского протокола.
     */
    public function testRegisterCustomProtocol(): void
    {
        $registry = new ProtocolRegistry();
        $custom = new ProtocolDefinition('customproto', 'Custom', 7, 'tcp', true, 'custom');
        $registry->register($custom);

        self::assertTrue($registry->has('CustomProto'));
        self::assertSame('Custom', $registry->get('customproto')->name());
        self::assertSame('tcp', $registry->get('customproto')->transport());
        self::assertTrue($registry->get('customproto')->isNativeConnectable());
        self::assertSame('custom', $registry->get('customproto')->defaultScheme());
        self::assertSame(7, $registry->get('customproto')->osiLayer());
    }

    /**
     * Неизвестный протокол → исключение.
     */
    public function testUnknownProtocolThrows(): void
    {
        $registry = new ProtocolRegistry();

        $this->expectException(ProtocolException::class);

        $registry->get('no-such-protocol-xyz');
    }

    /**
     * Генератор каталога отдаёт определения.
     */
    public function testCatalogDefinitionsGenerator(): void
    {
        $count = 0;

        foreach (ProtocolCatalog::definitions() as $definition) {
            self::assertNotSame('', $definition->id());
            ++$count;
        }

        self::assertSame(ProtocolCatalog::size(), $count);
    }

    /**
     * Отпечаток каталога: ловит мутации id/name/layer/transport/native/scheme.
     */
    public function testCatalogFingerprint(): void
    {
        $parts = [];

        foreach (ProtocolCatalog::definitions() as $definition) {
            $parts[] = implode('|', [
                $definition->id(),
                $definition->name(),
                (string) $definition->osiLayer(),
                $definition->transport(),
                $definition->isNativeConnectable() ? '1' : '0',
                $definition->defaultScheme(),
            ]);
        }

        self::assertCount(ProtocolCatalog::size(), $parts);
        self::assertSame(
            '6339fc90e17d86e927f665ec7a5cb4f2971a3bd4d597bf650d60dd2dcec796e8',
            hash('sha256', implode("\n", $parts)),
        );
    }
}

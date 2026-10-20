<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Exception\SecurityException;
use CloudCastle\ConnectManager\Security\EndpointGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Тесты SSRF-guard.
 */
#[CoversClass(EndpointGuard::class)]
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
final class EndpointGuardTest extends TestCase
{
    /**
     * Пустой endpoint запрещён.
     */
    public function testEmptyEndpointRejected(): void
    {
        $guard = new EndpointGuard();

        $this->expectException(SecurityException::class);

        $guard->assertAllowed('  ', ConnectionOptions::defaults());
    }

    /**
     * Управляющие символы запрещены.
     */
    public function testControlCharsRejected(): void
    {
        $guard = new EndpointGuard();

        $this->expectException(SecurityException::class);

        $guard->assertAllowed("evil\0host", ConnectionOptions::defaults());
    }

    /**
     * Unix-сокеты не проверяются как host.
     */
    public function testUnixPathAllowed(): void
    {
        $guard = new EndpointGuard();
        $guard->assertAllowed('/tmp/app.sock', ConnectionOptions::defaults());
        $guard->assertAllowed('unix:///tmp/app.sock', ConnectionOptions::defaults());

        self::assertNull($guard->extractHost('/tmp/app.sock'));
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function hostProvider(): iterable
    {
        yield 'public' => ['8.8.8.8', false];
        yield 'private' => ['10.0.0.1', true];
        yield 'localhost' => ['localhost', true];
        yield 'link-local-name' => ['0.0.0.0', true];
    }

    /**
     * Классификация host.
     */
    #[DataProvider('hostProvider')]
    public function testIsBlockedHost(string $host, bool $blocked): void
    {
        $guard = new EndpointGuard();

        self::assertSame($blocked, $guard->isBlockedHost($host));
    }

    /**
     * Извлечение host из URL и host:port.
     */
    public function testExtractHostVariants(): void
    {
        $guard = new EndpointGuard();

        self::assertSame('example.com', $guard->extractHost('tcp://example.com:443'));
        self::assertSame('example.com', $guard->extractHost('example.com:443'));
        self::assertSame('2001:db8::1', $guard->extractHost('[2001:db8::1]:443'));
        self::assertNull($guard->extractHost('udg://unused'));
        self::assertNull($guard->extractHost('[broken'));
    }

    /**
     * allowPrivateAddresses снимает блок.
     */
    public function testAllowPrivateBypassesGuard(): void
    {
        $guard = new EndpointGuard();
        $guard->assertAllowed('127.0.0.1:80', ConnectionOptions::defaults()->allowPrivateAddresses());

        $this->expectException(SecurityException::class);
        $guard->assertAllowed('127.0.0.1:80', ConnectionOptions::defaults());
    }
}

<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Exception\SecurityException;
use CloudCastle\ConnectManager\Security\EndpointGuard;
use CloudCastle\ConnectManager\Tests\Support\FixedDnsResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Тесты SSRF-guard с DNS-resolve и anti-rebinding.
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.ShortVariable")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
#[CoversClass(EndpointGuard::class)]
#[UsesClass(\CloudCastle\ConnectManager\Contract\ConnectionOptions::class)]
#[UsesClass(\CloudCastle\ConnectManager\Security\PinnedEndpoint::class)]
final class EndpointGuardTest extends TestCase
{
    /**
     * Пустой endpoint запрещён.
     */
    public function testEmptyEndpointRejected(): void
    {
        $guard = new EndpointGuard(new FixedDnsResolver());

        $this->expectException(SecurityException::class);

        $guard->assertAllowed('  ', ConnectionOptions::defaults());
    }

    /**
     * Управляющие символы запрещены.
     */
    public function testControlCharsRejected(): void
    {
        $guard = new EndpointGuard(new FixedDnsResolver());

        $this->expectException(SecurityException::class);

        $guard->assertAllowed("evil\0host", ConnectionOptions::defaults());
    }

    /**
     * Unix-сокеты не проверяются как host.
     */
    public function testUnixPathAllowed(): void
    {
        $guard = new EndpointGuard(new FixedDnsResolver());
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
     * Классификация host (IP и localhost без DNS).
     */
    #[DataProvider('hostProvider')]
    public function testIsBlockedHost(string $host, bool $blocked): void
    {
        $guard = new EndpointGuard(new FixedDnsResolver());

        self::assertSame($blocked, $guard->isBlockedHost($host));
    }

    /**
     * Hostname с публичным DNS проходит; с private — блокируется.
     */
    public function testDnsResolveBlocksPrivateAnswers(): void
    {
        $guard = new EndpointGuard(new FixedDnsResolver([
            'safe.example' => ['8.8.8.8'],
            'evil.example' => ['10.0.0.5'],
        ]));

        self::assertFalse($guard->isBlockedHost('safe.example'));
        self::assertTrue($guard->isBlockedHost('evil.example'));

        $pinned = $guard->prepare('safe.example:443', ConnectionOptions::defaults());
        self::assertSame('8.8.8.8:443', $pinned->connectEndpoint());
        self::assertSame('safe.example', $pinned->tlsPeerName());

        $this->expectException(SecurityException::class);
        $guard->prepare('evil.example:443', ConnectionOptions::defaults());
    }

    /**
     * Нерезолвящийся hostname запрещён при включённом SSRF-guard.
     */
    public function testUnresolvedHostBlocked(): void
    {
        $guard = new EndpointGuard(new FixedDnsResolver([]));

        $this->expectException(SecurityException::class);
        $guard->prepare('unknown.invalid', ConnectionOptions::defaults());
    }

    /**
     * Извлечение host из URL и host:port.
     */
    public function testExtractHostVariants(): void
    {
        $guard = new EndpointGuard(new FixedDnsResolver());

        self::assertSame('example.com', $guard->extractHost('tcp://example.com:443'));
        self::assertSame('example.com', $guard->extractHost('example.com:443'));
        self::assertSame('2001:db8::1', $guard->extractHost('[2001:db8::1]:443'));
        self::assertNull($guard->extractHost('udg://unused'));
        self::assertNull($guard->extractHost('[broken'));
    }

    /**
     * @return iterable<string, array{0: string, 1: string|null}>
     */
    public static function extractProvider(): iterable
    {
        yield 'url' => ['https://user:pass@Example.COM:8443/path', 'example.com'];
        yield 'ipv6-uri' => ['tcp://[2001:db8::2]:22', '2001:db8::2'];
        yield 'malformed-scheme' => ['not-a-url', 'not-a-url'];
        yield 'empty-host-url' => ['tcp:///path', null];
    }

    /**
     * Таблица-driven разбор malformed URL / credentials / IPv6.
     */
    #[DataProvider('extractProvider')]
    public function testExtractHostTable(string $endpoint, ?string $expected): void
    {
        $guard = new EndpointGuard(new FixedDnsResolver());

        self::assertSame($expected, $guard->extractHost($endpoint));
    }

    /**
     * allowPrivateAddresses снимает блок.
     */
    public function testAllowPrivateBypassesGuard(): void
    {
        $guard = new EndpointGuard(new FixedDnsResolver());
        $guard->assertAllowed('127.0.0.1:80', ConnectionOptions::defaults()->allowPrivateAddresses());

        $this->expectException(SecurityException::class);
        $guard->assertAllowed('127.0.0.1:80', ConnectionOptions::defaults());
    }

    /**
     * IPv6 pin оборачивает адрес в скобки.
     */
    public function testPinIpv6Host(): void
    {
        $guard = new EndpointGuard(new FixedDnsResolver([
            'v6.example' => ['2001:4860:4860::8888'],
        ]));
        $pinned = $guard->prepare('v6.example:443', ConnectionOptions::defaults());

        self::assertSame('[2001:4860:4860::8888]:443', $pinned->connectEndpoint());
        self::assertSame('v6.example', $pinned->tlsPeerName());
    }
}

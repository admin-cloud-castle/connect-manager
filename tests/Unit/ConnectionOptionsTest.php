<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\Contract\AuthCredentials;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Тесты ConnectionOptions.
 */
#[CoversClass(ConnectionOptions::class)]
#[UsesClass(AuthCredentials::class)]
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
final class ConnectionOptionsTest extends TestCase
{
    /**
     * Значения по умолчанию и withers.
     */
    public function testDefaultsAndWithers(): void
    {
        $options = ConnectionOptions::defaults();

        self::assertSame(5.0, $options->connectTimeout());
        self::assertSame(30.0, $options->ioTimeout());
        self::assertTrue($options->verifyPeer());
        self::assertTrue($options->blockPrivateAddresses());
        self::assertFalse($options->allowInsecure());
        self::assertSame([], $options->streamContext());
        self::assertSame('sync', $options->mode());

        $async = $options->withMode('async');
        self::assertSame('async', $async->mode());
        self::assertNotSame($async->mode(), $options->mode());

        $local = $options->allowPrivateAddresses();
        self::assertFalse($local->blockPrivateAddresses());
    }

    /**
     * Некорректные таймауты нормализуются.
     */
    public function testInvalidTimeoutsNormalized(): void
    {
        $options = new ConnectionOptions(-1.0, -5.0, false, false, true, ['ssl' => []], 'weird');

        self::assertSame(5.0, $options->connectTimeout());
        self::assertSame(30.0, $options->ioTimeout());
        self::assertFalse($options->verifyPeer());
        self::assertTrue($options->allowInsecure());
        self::assertNotSame('async', $options->mode());
        self::assertArrayHasKey('ssl', $options->streamContext());
    }

    /**
     * auth(), withAuth и withTlsPeerName поверх существующего ssl-контекста.
     */
    public function testAuthAndTlsPeerName(): void
    {
        $auth = AuthCredentials::withPassword('user', 'secret');
        $base = ConnectionOptions::defaults()->withAuth($auth);
        self::assertSame($auth, $base->auth());

        $merged = new ConnectionOptions(
            5.0,
            30.0,
            true,
            true,
            false,
            ['ssl' => ['verify_peer' => true]],
        );
        $withTls = $merged->withTlsPeerName('api.example');
        $context = $withTls->streamContext();
        self::assertArrayHasKey('ssl', $context);
        self::assertIsArray($context['ssl']);
        /** @var array<string, mixed> $ssl */
        $ssl = $context['ssl'];
        self::assertSame('api.example', $ssl['peer_name']);

        $copyOnly = ConnectionOptions::defaults()->withMode('sync');
        self::assertNull($copyOnly->auth());
    }
}

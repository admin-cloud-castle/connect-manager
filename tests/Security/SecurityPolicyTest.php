<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Security;

use CloudCastle\ConnectManager\ConnectManager;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Exception\SecurityException;
use CloudCastle\ConnectManager\Security\EndpointGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Тесты политики безопасности.
 */
#[CoversClass(EndpointGuard::class)]
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
#[UsesClass(\CloudCastle\ConnectManager\Security\PhpDnsResolver::class)]
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
final class SecurityPolicyTest extends TestCase
{
    /**
     * Блокировка частных диапазонов и metadata URI.
     */
    public function testBlocksPrivateAndMetadataHosts(): void
    {
        $manager = new ConnectManager();

        foreach (['10.1.2.3:80', '192.168.0.1:80', '169.254.169.254:80', 'localhost:80'] as $endpoint) {
            try {
                $manager->connect('http', $endpoint);
                self::fail('должен быть SecurityException для ' . $endpoint);
            } catch (SecurityException $exception) {
                self::assertStringContainsString('SSRF', $exception->getMessage());
            }
        }
    }

    /**
     * TLS verify включён по умолчанию.
     */
    public function testTlsVerifyDefaultsOn(): void
    {
        $options = ConnectionOptions::defaults();
        self::assertTrue($options->verifyPeer());
        self::assertFalse($options->allowInsecure());
    }

    /**
     * Публичный host проходит guard (без реального connect).
     */
    public function testPublicHostPassesGuard(): void
    {
        $guard = new EndpointGuard();
        $guard->assertAllowed('example.com:443', ConnectionOptions::defaults());
        self::assertSame('example.com', $guard->extractHost('https://example.com/path'));
    }
}

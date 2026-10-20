<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\Driver\TransportResolver;
use CloudCastle\ConnectManager\Exception\ProtocolException;
use CloudCastle\ConnectManager\Protocol\ProtocolDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Тесты TransportResolver.
 */
#[CoversClass(TransportResolver::class)]
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
final class TransportResolverTest extends TestCase
{
    /**
     * TCP и HTTP URI.
     */
    public function testResolveTcpAndHttp(): void
    {
        $resolver = new TransportResolver();
        $tcp = new ProtocolDefinition('tcp', 'TCP', 4, 'tcp', true, 'tcp');
        $http = new ProtocolDefinition('http', 'HTTP', 7, 'tcp', true, 'http');

        self::assertSame('tcp://example.com:80', $resolver->resolve($tcp, 'example.com:80'));
        self::assertSame('tcp://example.com:80', $resolver->resolve($http, 'example.com'));
        self::assertSame('tls://example.com:443', $resolver->resolve(
            new ProtocolDefinition('https', 'HTTPS', 7, 'tcp', true, 'https'),
            'example.com',
        ));
    }

    /**
     * Готовый URI не переписывается.
     */
    public function testPassthroughUri(): void
    {
        $resolver = new TransportResolver();
        $tcp = new ProtocolDefinition('tcp', 'TCP', 4, 'tcp', true, 'tcp');

        self::assertSame('tcp://a:1', $resolver->resolve($tcp, 'tcp://a:1'));
    }

    /**
     * Unix path.
     */
    public function testUnix(): void
    {
        $resolver = new TransportResolver();
        $unix = new ProtocolDefinition('unix', 'Unix', 0, 'unix', true, 'unix');
        $udg = new ProtocolDefinition('udg', 'UDG', 0, 'unix', true, 'udg');

        self::assertSame('unix:///tmp/x.sock', $resolver->resolve($unix, '/tmp/x.sock'));
        self::assertSame('udg:///tmp/x.sock', $resolver->resolve($udg, '/tmp/x.sock'));
    }

    /**
     * IPv6 endpoint.
     */
    public function testIpv6Endpoint(): void
    {
        $resolver = new TransportResolver();
        $tcp = new ProtocolDefinition('tcp', 'TCP', 4, 'tcp', true, 'tcp');
        $http = new ProtocolDefinition('http', 'HTTP', 7, 'tcp', true, 'http');

        self::assertSame('tcp://[2001:db8::1]:8080', $resolver->resolve($tcp, '[2001:db8::1]:8080'));
        self::assertSame('tcp://[2001:db8::1]:80', $resolver->resolve($http, '[2001:db8::1]'));
    }

    /**
     * Физический протокол без схемы → исключение.
     */
    public function testNonNativeThrows(): void
    {
        $resolver = new TransportResolver();
        $adsl = new ProtocolDefinition('adsl', 'ADSL', 1, 'none', false, 'adsl');

        $this->expectException(ProtocolException::class);
        $resolver->resolve($adsl, 'line');
    }

    /**
     * socket → tcp.
     */
    public function testSocketMapsToTcp(): void
    {
        $resolver = new TransportResolver();
        $socket = new ProtocolDefinition('socket', 'Socket', 0, 'socket', true, 'socket');

        self::assertSame('tcp://example.com:1234', $resolver->resolve($socket, 'example.com:1234'));
    }
}

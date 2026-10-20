<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Security\EndpointGuard;
use CloudCastle\ConnectManager\Tests\Support\FixedDnsResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Приватные ветки EndpointGuard (replaceHost / replaceHostInUrl).
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
#[CoversClass(EndpointGuard::class)]
#[UsesClass(ConnectionOptions::class)]
final class EndpointGuardCoverageTest extends TestCase
{
    /**
     * replaceHost для IPv6 endpoint с поломанной скобкой.
     */
    public function testReplaceHostBrokenBracket(): void
    {
        $method = new ReflectionMethod(EndpointGuard::class, 'replaceHost');
        $guard = new EndpointGuard(new FixedDnsResolver());
        $result = $method->invoke($guard, '[broken', '8.8.8.8');
        self::assertSame('8.8.8.8', $result);

        $ipv6 = $method->invoke($guard, '[2001:db8::1]:443', '2001:db8::2');
        self::assertSame('[2001:db8::2]:443', $ipv6);

        $brokenEnd = $method->invoke($guard, '[2001:db8::1', '1.2.3.4');
        self::assertSame('1.2.3.4', $brokenEnd);
    }

    /**
     * replaceHostInUrl при неразбираемом URI.
     */
    public function testReplaceHostInUrlMalformed(): void
    {
        $method = new ReflectionMethod(EndpointGuard::class, 'replaceHostInUrl');
        $guard = new EndpointGuard(new FixedDnsResolver());
        $endpoint = 'http://';
        $result = $method->invoke($guard, $endpoint, '1.2.3.4');
        self::assertSame($endpoint, $result);
    }

    /**
     * replaceHost для host:port без схемы.
     */
    public function testReplaceHostPlainPort(): void
    {
        $method = new ReflectionMethod(EndpointGuard::class, 'replaceHost');
        $guard = new EndpointGuard(new FixedDnsResolver());
        $result = $method->invoke($guard, 'host.example:8443', '8.8.4.4');
        self::assertSame('8.8.4.4:8443', $result);

        $plainHost = $method->invoke($guard, 'hostonly', '9.9.9.9');
        self::assertSame('9.9.9.9', $plainHost);
    }

    /**
     * assertConnectedPeer с пустым peer name.
     */
    public function testAssertConnectedPeerEmptyPeerName(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($pair);
        $guard = new EndpointGuard(new FixedDnsResolver());
        $guard->assertConnectedPeer($pair[0], ConnectionOptions::defaults()->allowPrivateAddresses());
        fclose($pair[0]);
        fclose($pair[1]);
    }

    /**
     * assertConnectedPeer: extractHost(peer) === null.
     */
    public function testAssertConnectedPeerUnparseablePeer(): void
    {
        $guard = new EndpointGuard(new FixedDnsResolver());
        $stream = fopen('php://memory', 'r+b');
        self::assertNotFalse($stream);
        $guard->assertConnectedPeer($stream, ConnectionOptions::defaults());
        fclose($stream);
    }
}

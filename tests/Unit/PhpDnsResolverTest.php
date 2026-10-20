<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\Security\PhpDnsResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Системный DNS-резолвер (A/AAAA и fallback).
 */
#[CoversClass(PhpDnsResolver::class)]
final class PhpDnsResolverTest extends TestCase
{
    /**
     * IP literal возвращается без DNS-запроса.
     */
    public function testResolveIpLiteral(): void
    {
        $resolver = new PhpDnsResolver();

        self::assertSame(['8.8.8.8'], $resolver->resolve('8.8.8.8'));
    }

    /**
     * Резолв публичного имени (dns_get_record или gethostbynamel).
     */
    public function testResolvePublicHostname(): void
    {
        $resolver = new PhpDnsResolver();
        $ips = $resolver->resolve('dns.google');

        self::assertNotSame([], $ips);
        self::assertContainsOnly('string', $ips);
    }

    /**
     * Несуществующий host → пустой список.
     */
    public function testResolveUnknownReturnsEmpty(): void
    {
        $resolver = new PhpDnsResolver();

        self::assertSame([], $resolver->resolve('this-host-should-not-exist.invalid.'));
    }

    /**
     * Имя с AAAA (Cloudflare DNS).
     */
    public function testResolveIpv6CapableHost(): void
    {
        $resolver = new PhpDnsResolver();
        $ips = $resolver->resolve('one.one.one.one');
        self::assertNotSame([], $ips);

        $v6 = $resolver->resolve('ipv6.google.com');
        self::assertNotSame([], $v6);

        $local = $resolver->resolve('localhost');
        self::assertContains('127.0.0.1', $local);
    }

    /**
     * ipFromDnsRow для AAAA и fromGethostbynamel fallback.
     */
    public function testPrivateDnsHelpers(): void
    {
        $resolver = new PhpDnsResolver();
        $rowMethod = new ReflectionMethod(PhpDnsResolver::class, 'ipFromDnsRow');
        self::assertSame(
            '2001:db8::1',
            $rowMethod->invoke($resolver, ['type' => 'AAAA', 'ipv6' => '2001:db8::1']),
        );
        self::assertNull($rowMethod->invoke($resolver, ['type' => 'MX', 'target' => 'mail.example']));

        $fallback = new ReflectionMethod(PhpDnsResolver::class, 'fromGethostbynamel');
        $ips = $fallback->invoke($resolver, 'localhost');
        self::assertIsArray($ips);
        self::assertContains('127.0.0.1', $ips);
    }
}

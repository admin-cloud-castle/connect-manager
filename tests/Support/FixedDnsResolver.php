<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Support;

use CloudCastle\ConnectManager\Contract\DnsResolverInterface;

/**
 * DNS-резолвер с фиксированной картой host → IP для тестов.
 */
final class FixedDnsResolver implements DnsResolverInterface
{
    /**
     * @param array<string, list<string>> $map Карта hostname → адреса
     */
    public function __construct(private readonly array $map = [])
    {
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        return $this->map[strtolower($host)] ?? [];
    }
}

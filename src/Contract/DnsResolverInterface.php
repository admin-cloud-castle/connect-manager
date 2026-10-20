<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Contract;

/**
 * Резолвер DNS для SSRF-guard и защиты от DNS-rebinding.
 */
interface DnsResolverInterface
{
    /**
     * Возвращает список IPv4/IPv6 адресов для hostname.
     *
     * @return list<string>
     */
    public function resolve(string $host): array;
}

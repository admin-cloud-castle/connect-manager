<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Security;

/**
 * Endpoint после SSRF-проверки: IP для connect и исходный host для TLS SNI.
 */
final class PinnedEndpoint
{
    /**
     * @param string      $connectEndpoint Endpoint с подставленным IP (host:port или URI)
     * @param string|null $tlsPeerName     Исходное имя для verify_peer_name / SNI
     * @param list<string> $resolvedIps    Все проверенные публичные IP
     */
    public function __construct(
        private readonly string $connectEndpoint,
        private readonly ?string $tlsPeerName,
        private readonly array $resolvedIps,
    ) {
    }

    /**
     * Endpoint для stream_socket_client (IP-пин).
     */
    public function connectEndpoint(): string
    {
        return $this->connectEndpoint;
    }

    /**
     * Имя для TLS peer verification (SNI), если hostname резолвился.
     */
    public function tlsPeerName(): ?string
    {
        return $this->tlsPeerName;
    }

    /**
     * Проверенные публичные адреса.
     *
     * @return list<string>
     */
    public function resolvedIps(): array
    {
        return $this->resolvedIps;
    }
}

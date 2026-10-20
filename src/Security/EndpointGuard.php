<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Security;

use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Contract\DnsResolverInterface;
use CloudCastle\ConnectManager\Exception\SecurityException;

/**
 * Валидация endpoint, DNS-resolve и защита от SSRF / DNS-rebinding.
 *
 * @SuppressWarnings("PHPMD.CyclomaticComplexity")
 * @SuppressWarnings("PHPMD.NPathComplexity")
 * @SuppressWarnings("PHPMD.ShortVariable")
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 * @SuppressWarnings("PHPMD.ErrorControlOperator")
 */
final class EndpointGuard
{
    /**
     * @param DnsResolverInterface $dns Резолвер DNS (по умолчанию системный)
     */
    public function __construct(
        private readonly DnsResolverInterface $dns = new PhpDnsResolver(),
    ) {
    }

    /**
     * Проверяет endpoint и возвращает IP-pinned вариант для connect.
     *
     * @throws SecurityException
     */
    public function prepare(string $endpoint, ConnectionOptions $options): PinnedEndpoint
    {
        $endpoint = trim($endpoint);
        $this->assertEndpointShape($endpoint);

        if (!$options->blockPrivateAddresses()) {
            return new PinnedEndpoint($endpoint, null, []);
        }

        $host = $this->extractHost($endpoint);

        if ($host === null) {
            return new PinnedEndpoint($endpoint, null, []);
        }

        $ips = $this->resolvePublicIps($host);
        $pinnedHost = $ips[0];
        $connectEndpoint = $this->replaceHost($endpoint, $pinnedHost);
        $tlsPeerName = filter_var($host, FILTER_VALIDATE_IP) === false ? $host : null;

        return new PinnedEndpoint($connectEndpoint, $tlsPeerName, $ips);
    }

    /**
     * Проверяет endpoint согласно опциям безопасности (без пиннинга).
     *
     * @throws SecurityException
     */
    public function assertAllowed(string $endpoint, ConnectionOptions $options): void
    {
        $this->prepare($endpoint, $options);
    }

    /**
     * Повторная проверка адреса пира после connect (анти-rebinding).
     *
     * @param mixed $stream Открытый stream resource
     *
     * @throws SecurityException
     */
    public function assertConnectedPeer(mixed $stream, ConnectionOptions $options): void
    {
        if (!$options->blockPrivateAddresses() || !is_resource($stream)) {
            return;
        }

        $peer = @stream_socket_get_name($stream, true);

        if (!is_string($peer) || $peer === '') {
            return;
        }

        $host = $this->extractHost($peer);

        if ($host === null) {
            return;
        }

        if ($this->isBlockedIp($host)) {
            throw new SecurityException(sprintf('Пир после connect запрещён (SSRF-guard): %s', $host));
        }
    }

    /**
     * Извлекает host из endpoint (host:port, scheme://host, unix-path).
     */
    public function extractHost(string $endpoint): ?string
    {
        if ($this->isUnixLike($endpoint)) {
            return null;
        }

        if (str_contains($endpoint, '://')) {
            return $this->hostFromUrl($endpoint);
        }

        if (str_starts_with($endpoint, '[')) {
            return $this->hostFromBracket($endpoint);
        }

        return $this->hostFromHostPort($endpoint);
    }

    /**
     * Проверяет, является ли host частным/зарезервированным (с DNS для имён).
     */
    public function isBlockedHost(string $host): bool
    {
        if ($this->isLocalHostname($host)) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->isBlockedIp($host);
        }

        try {
            $this->resolvePublicIps($host);
        } catch (SecurityException) {
            return true;
        }

        return false;
    }

    /**
     * Резолвит hostname и проверяет, что все адреса публичные.
     *
     * @return list<string>
     *
     * @throws SecurityException
     */
    public function resolvePublicIps(string $host): array
    {
        if ($this->isLocalHostname($host)) {
            throw new SecurityException(sprintf('Запрещённый адрес (SSRF-guard): %s', $host));
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if ($this->isBlockedIp($host)) {
                throw new SecurityException(sprintf('Запрещённый адрес (SSRF-guard): %s', $host));
            }

            return [$host];
        }

        $ips = $this->dns->resolve($host);

        if ($ips === []) {
            throw new SecurityException(sprintf('Не удалось резолвить host (SSRF-guard): %s', $host));
        }

        foreach ($ips as $ip) {
            if ($this->isBlockedIp($ip)) {
                throw new SecurityException(sprintf(
                    'DNS вернул запрещённый адрес (SSRF-guard): %s → %s',
                    $host,
                    $ip,
                ));
            }
        }

        return $ips;
    }

    /**
     * Базовая форма endpoint.
     *
     * @throws SecurityException
     */
    private function assertEndpointShape(string $endpoint): void
    {
        if ($endpoint === '') {
            throw new SecurityException('Пустой endpoint запрещён');
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $endpoint) === 1) {
            throw new SecurityException('Endpoint содержит управляющие символы');
        }
    }

    /**
     * Unix / UDG пути не резолвятся как host.
     */
    private function isUnixLike(string $endpoint): bool
    {
        return str_starts_with($endpoint, '/')
            || str_starts_with($endpoint, 'unix://')
            || str_starts_with($endpoint, 'udg://');
    }

    /**
     * localhost и синонимы.
     */
    private function isLocalHostname(string $host): bool
    {
        return $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || $host === '0.0.0.0';
    }

    /**
     * Host из URL.
     */
    private function hostFromUrl(string $endpoint): ?string
    {
        $parts = parse_url($endpoint);
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;

        if (!is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower($host);

        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            return substr($host, 1, -1);
        }

        return $host;
    }

    /**
     * Host из [IPv6]:port.
     */
    private function hostFromBracket(string $endpoint): ?string
    {
        $end = strpos($endpoint, ']');

        if ($end === false) {
            return null;
        }

        return strtolower(substr($endpoint, 1, $end - 1));
    }

    /**
     * Host из host:port.
     */
    private function hostFromHostPort(string $endpoint): string
    {
        $candidate = $endpoint;
        $colon = strrpos($candidate, ':');

        if (is_int($colon) && ctype_digit(substr($candidate, $colon + 1))) {
            $candidate = substr($candidate, 0, $colon);
        }

        return strtolower($candidate);
    }

    /**
     * Подставляет IP вместо hostname в endpoint.
     */
    private function replaceHost(string $endpoint, string $ip): string
    {
        $formattedIp = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;

        if (str_contains($endpoint, '://')) {
            return $this->replaceHostInUrl($endpoint, $formattedIp);
        }

        if (str_starts_with($endpoint, '[')) {
            $end = strpos($endpoint, ']');

            if ($end === false) {
                return $formattedIp;
            }

            return $formattedIp . substr($endpoint, $end + 1);
        }

        $colon = strrpos($endpoint, ':');

        if (is_int($colon) && ctype_digit(substr($endpoint, $colon + 1))) {
            return $formattedIp . substr($endpoint, $colon);
        }

        return $formattedIp;
    }

    /**
     * Замена host в URI.
     */
    private function replaceHostInUrl(string $endpoint, string $formattedIp): string
    {
        $parts = parse_url($endpoint);

        if (!is_array($parts)) {
            return $endpoint;
        }

        $scheme = $parts['scheme'] ?? 'tcp';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return $scheme . '://' . $formattedIp . $port . $path . $query;
    }

    /**
     * IP частный/reserved/link-local.
     */
    private function isBlockedIp(string $ip): bool
    {
        return !$this->isPublicIp($ip);
    }

    /**
     * Проверяет, что IP публичный (не private/reserved/link-local).
     */
    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}

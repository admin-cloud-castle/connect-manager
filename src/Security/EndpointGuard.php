<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Security;

use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Exception\SecurityException;

/**
 * Валидация endpoint и защита от SSRF.
 * @SuppressWarnings("PHPMD.CyclomaticComplexity")
 * @SuppressWarnings("PHPMD.NPathComplexity")
 * @SuppressWarnings("PHPMD.ShortVariable")
 */
final class EndpointGuard
{
    /**
     * Проверяет endpoint согласно опциям безопасности.
     *
     * @throws SecurityException
     */
    public function assertAllowed(string $endpoint, ConnectionOptions $options): void
    {
        $endpoint = trim($endpoint);

        if ($endpoint === '') {
            throw new SecurityException('Пустой endpoint запрещён');
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $endpoint) === 1) {
            throw new SecurityException('Endpoint содержит управляющие символы');
        }

        if (!$options->blockPrivateAddresses()) {
            return;
        }

        $host = $this->extractHost($endpoint);

        if ($host === null) {
            return;
        }

        if ($this->isBlockedHost($host)) {
            throw new SecurityException(sprintf('Запрещённый адрес (SSRF-guard): %s', $host));
        }
    }

    /**
     * Извлекает host из endpoint (host:port, scheme://host, unix-path).
     */
    public function extractHost(string $endpoint): ?string
    {
        if (
            str_starts_with($endpoint, '/')
            || str_starts_with($endpoint, 'unix://')
            || str_starts_with($endpoint, 'udg://')
        ) {
            return null;
        }

        $candidate = $endpoint;

        if (str_contains($candidate, '://')) {
            $parts = parse_url($candidate);
            $host = is_array($parts) ? ($parts['host'] ?? null) : null;

            if (!is_string($host) || $host === '') {
                return null;
            }

            return strtolower($host);
        }

        if (str_starts_with($candidate, '[')) {
            $end = strpos($candidate, ']');

            if ($end === false) {
                return null;
            }

            return strtolower(substr($candidate, 1, $end - 1));
        }

        $colon = strrpos($candidate, ':');

        if (is_int($colon) && ctype_digit(substr($candidate, $colon + 1))) {
            $candidate = substr($candidate, 0, $colon);
        }

        return strtolower($candidate);
    }

    /**
     * Проверяет, является ли host частным/зарезервированным.
     */
    public function isBlockedHost(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || $host === '0.0.0.0') {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return !$this->isPublicIp($host);
        }

        return false;
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

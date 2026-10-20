<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Driver;

use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Exception\ConnectionException;
use CloudCastle\ConnectManager\Exception\TimeoutException;

/**
 * Открывает PHP stream по URI с учётом TLS и таймаутов.
 *
 * @SuppressWarnings("PHPMD.ErrorControlOperator")
 */
final class StreamOpener
{
    /**
     * Открывает клиентский stream.
     *
     * @return resource
     *
     * @throws ConnectionException
     * @throws TimeoutException
     */
    public function open(string $uri, ConnectionOptions $options): mixed
    {
        $context = stream_context_create($this->buildContextOptions($options));
        $stream = @stream_socket_client(
            $uri,
            timeout: $options->connectTimeout(),
            flags: STREAM_CLIENT_CONNECT,
            context: $context,
        );

        if ($stream === false) {
            throw $this->createOpenException($uri, null, $this->lastErrorMessage());
        }

        $appliedTimeout = $this->applyIoTimeout($stream, $options->ioTimeout());
        @stream_set_timeout($stream, $appliedTimeout);

        return $stream;
    }

    /**
     * Текст последней PHP-ошибки или пустая строка.
     */
    public function lastErrorMessage(): string
    {
        $last = error_get_last();

        if (!is_array($last)) {
            return '';
        }

        return $last['message'];
    }

    /**
     * Определяет, является ли ошибка таймаутом.
     */
    public function isTimeoutError(int $errno, string $errstr): bool
    {
        return $errno === SOCKET_ETIMEDOUT || str_contains(strtolower($errstr), 'timed out');
    }

    /**
     * Формирует исключение при ошибке открытия.
     *
     * @throws TimeoutException
     * @throws ConnectionException
     */
    public function createOpenException(string $uri, ?int $code, ?string $message): ConnectionException
    {
        $resolvedCode = $code ?? 0;
        $resolvedMessage = $message ?? '';

        if ($this->isTimeoutError($resolvedCode, $resolvedMessage)) {
            throw new TimeoutException(sprintf('Таймаут соединения: %s', $uri));
        }

        return new ConnectionException(sprintf(
            'Не удалось открыть %s: [%d] %s',
            $uri,
            $resolvedCode,
            $resolvedMessage,
        ));
    }

    /**
     * Опции stream context.
     *
     * @return array<string, mixed>
     */
    public function buildContextOptions(ConnectionOptions $options): array
    {
        $verify = $options->verifyPeer();
        $insecure = $options->allowInsecure();

        /** @var array<string, mixed> $defaults */
        $defaults = [
            'socket' => [
                'tcp_nodelay' => true,
            ],
            'ssl' => [
                'verify_peer' => $verify,
                'verify_peer_name' => $verify,
                'allow_self_signed' => $insecure,
                'security_level' => $insecure ? 0 : 2,
            ],
        ];

        /** @var array<string, mixed> $merged */
        $merged = array_replace_recursive($defaults, $options->streamContext());

        return $merged;
    }

    /**
     * Применяет I/O timeout к stream (целые секунды).
     *
     * @param mixed $stream Ожидается stream resource
     *
     * @return int Фактически запрошенные секунды
     *
     * @throws ConnectionException
     */
    public function applyIoTimeout(mixed $stream, float $ioTimeout): int
    {
        if (!is_resource($stream)) {
            throw new ConnectionException('Ожидался stream resource');
        }

        $seconds = $this->resolveIoTimeoutSeconds($ioTimeout);
        @stream_set_timeout($stream, $seconds);

        return $seconds;
    }

    /**
     * Целые секунды I/O timeout (минимум 1).
     */
    public function resolveIoTimeoutSeconds(float $ioTimeout): int
    {
        $seconds = (int) $ioTimeout;

        return $seconds > 0 ? $seconds : 1;
    }
}

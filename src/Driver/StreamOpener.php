<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Driver;

use CloudCastle\ConnectManager\Contract\AsyncRuntimeInterface;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Exception\ConnectionException;
use CloudCastle\ConnectManager\Exception\TimeoutException;

/**
 * Открывает PHP stream по URI с учётом TLS, таймаутов и async connect.
 *
 * @SuppressWarnings("PHPMD.ErrorControlOperator")
 * @SuppressWarnings("PHPMD.CyclomaticComplexity")
 */
final class StreamOpener
{
    /**
     * Открывает клиентский stream (sync или non-blocking async).
     *
     * @return resource
     *
     * @throws ConnectionException
     * @throws TimeoutException
     */
    public function open(
        string $uri,
        ConnectionOptions $options,
        ?AsyncRuntimeInterface $async = null,
    ): mixed {
        $context = stream_context_create($this->buildContextOptions($options));
        $useAsync = $options->mode() === 'async' && $async instanceof AsyncRuntimeInterface;
        $flags = STREAM_CLIENT_CONNECT;

        if ($useAsync) {
            $flags |= STREAM_CLIENT_ASYNC_CONNECT;
        }

        $stream = @stream_socket_client(
            $uri,
            timeout: $useAsync ? 0.0 : $options->connectTimeout(),
            flags: $flags,
            context: $context,
        );

        if ($stream === false) {
            throw $this->createOpenException($uri, null, $this->lastErrorMessage());
        }

        if ($useAsync) {
            $this->completeAsyncConnect($stream, $uri, $options, $async);
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
        $socketTimedOut = defined('SOCKET_ETIMEDOUT') && $errno === SOCKET_ETIMEDOUT;

        return $socketTimedOut || str_contains(strtolower($errstr), 'timed out');
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
     * Опции stream context (TLS verify peer по умолчанию из ConnectionOptions).
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

        $merged = array_replace_recursive($defaults, $options->streamContext());

        /** @var array<string, mixed> $merged */
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

    /**
     * Дожидается готовности writable после STREAM_CLIENT_ASYNC_CONNECT.
     *
     * @param resource $stream
     *
     * @throws ConnectionException
     * @throws TimeoutException
     */
    /**
     * Дожидается готовности writable после STREAM_CLIENT_ASYNC_CONNECT.
     *
     * @throws ConnectionException
     * @throws TimeoutException
     */
    private function completeAsyncConnect(
        mixed $stream,
        string $uri,
        ConnectionOptions $options,
        AsyncRuntimeInterface $async,
    ): void {
        if (!is_resource($stream)) {
            throw new ConnectionException('Ожидался stream resource');
        }

        stream_set_blocking($stream, false);
        $async->awaitWritable($stream, $options->connectTimeout());
        $peer = @stream_socket_get_name($stream, true);

        if (!is_string($peer) || $peer === '') {
            fclose($stream);

            throw $this->createOpenException($uri, null, 'async connect не установил пир');
        }
    }
}

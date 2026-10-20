<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Contract;

/**
 * Опции открытия и эксплуатации соединения.
 * @SuppressWarnings("PHPMD.BooleanArgumentFlag")
 * @SuppressWarnings("PHPMD.LongVariable")
 */
final class ConnectionOptions
{
    /**
     * Таймаут соединения в секундах.
     */
    private readonly float $connectTimeout;

    /**
     * Таймаут чтения/записи в секундах.
     */
    private readonly float $ioTimeout;

    /**
     * Режим: sync или async-aware (не блокирует фабрику, влияет на runtime).
     */
    private readonly string $mode;

    /**
     * @param array<string, mixed> $streamContext
     */
    public function __construct(
        float $connectTimeout = 5.0,
        float $ioTimeout = 30.0,
        /**
         * Проверять TLS peer name / certificate.
         */
        private readonly bool $verifyPeer = true,
        /**
         * Блокировать частные/link-local адреса (анти-SSRF).
         */
        private readonly bool $blockPrivateAddresses = true,
        /**
         * Разрешить plaintext без TLS для схем, где TLS опционален.
         */
        private readonly bool $allowInsecure = false,
        /**
         * Дополнительные флаги контекста stream.
         */
        private readonly array $streamContext = [],
        string $mode = 'sync',
    ) {
        $this->connectTimeout = $connectTimeout > 0.0 ? $connectTimeout : 5.0;
        $this->ioTimeout = $ioTimeout > 0.0 ? $ioTimeout : 30.0;
        $this->mode = $mode === 'async' ? 'async' : 'sync';
    }

    /**
     * Создаёт опции с безопасными значениями по умолчанию.
     */
    public static function defaults(): self
    {
        return new self();
    }

    /**
     * Возвращает таймаут connect.
     */
    public function connectTimeout(): float
    {
        return $this->connectTimeout;
    }

    /**
     * Возвращает таймаут I/O.
     */
    public function ioTimeout(): float
    {
        return $this->ioTimeout;
    }

    /**
     * Нужна ли проверка TLS peer.
     */
    public function verifyPeer(): bool
    {
        return $this->verifyPeer;
    }

    /**
     * Блокируются ли частные адреса.
     */
    public function blockPrivateAddresses(): bool
    {
        return $this->blockPrivateAddresses;
    }

    /**
     * Разрешён ли небезопасный plaintext.
     */
    public function allowInsecure(): bool
    {
        return $this->allowInsecure;
    }

    /**
     * Доп. контекст stream.
     *
     * @return array<string, mixed>
     */
    public function streamContext(): array
    {
        return $this->streamContext;
    }

    /**
     * Режим sync|async.
     */
    public function mode(): string
    {
        return $this->mode;
    }

    /**
     * Копия с другим режимом.
     */
    public function withMode(string $mode): self
    {
        return new self(
            $this->connectTimeout,
            $this->ioTimeout,
            $this->verifyPeer,
            $this->blockPrivateAddresses,
            $this->allowInsecure,
            $this->streamContext,
            $mode,
        );
    }

    /**
     * Копия с отключённой блокировкой частных адресов (для тестов/localhost).
     */
    public function allowPrivateAddresses(): self
    {
        return new self(
            $this->connectTimeout,
            $this->ioTimeout,
            $this->verifyPeer,
            false,
            $this->allowInsecure,
            $this->streamContext,
            $this->mode,
        );
    }
}

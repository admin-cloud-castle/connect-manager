<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Support;

use CloudCastle\ConnectManager\Contract\ConnectionInterface;
use CloudCastle\ConnectManager\Exception\ConnectionException;

/**
 * Connection, имитирующий сбой записи (write возвращает 0).
 */
final class ZeroWriteConnection implements ConnectionInterface
{
    private bool $open = true;

    /**
     * @param string $response Данные, которые вернёт read()
     */
    public function __construct(private readonly string $response = '')
    {
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function protocol(): string
    {
        return 'http';
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function osiLayer(): int
    {
        return 7;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function isOpen(): bool
    {
        return $this->open;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function remoteEndpoint(): string
    {
        return '127.0.0.1:80';
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function write(string $payload): int
    {
        unset($payload);
        $this->assertOpen();

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function read(int $length): string
    {
        $this->assertOpen();

        if ($this->response === '') {
            return '';
        }

        return substr($this->response, 0, $length);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function readChunks(int $chunkSize = 8192): \Generator
    {
        yield $this->read($chunkSize);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function close(): void
    {
        $this->open = false;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function metadata(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function resource(): mixed
    {
        return null;
    }

    /**
     * @throws ConnectionException
     */
    private function assertOpen(): void
    {
        if (!$this->open) {
            throw new ConnectionException('Соединение закрыто');
        }
    }
}

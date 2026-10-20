<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Support;

use CloudCastle\ConnectManager\Contract\ConnectionInterface;
use CloudCastle\ConnectManager\Exception\ConnectionException;

/**
 * In-memory Connection для тестов прикладного HTTP-слоя.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
final class BufferConnection implements ConnectionInterface
{
    private string $inbox = '';

    private bool $open = true;

    /**
     * @param string $response Данные, которые вернёт read()
     */
    public function __construct(private string $response)
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
        $this->assertOpen();
        $this->inbox .= $payload;

        return strlen($payload);
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

        $chunk = substr($this->response, 0, $length);
        $this->response = substr($this->response, strlen($chunk));

        return $chunk;
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
     * Накопленный исходящий запрос.
     */
    public function written(): string
    {
        return $this->inbox;
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

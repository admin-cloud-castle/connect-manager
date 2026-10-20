<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Support;

use CloudCastle\ConnectManager\Contract\ConnectionInterface;
use CloudCastle\ConnectManager\Exception\ConnectionException;

/**
 * Connection, отдающая read()-ответ по шагам (для веток HttpClient::readResponse).
 *
 * @param list<string> $steps
 */
final class IncrementalBufferConnection implements ConnectionInterface
{
    private int $step = 0;

    private bool $open = true;

    /**
     * @param list<string> $steps Очередь фрагментов для read()
     */
    public function __construct(private array $steps)
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

        return 1;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function read(int $length): string
    {
        unset($length);
        $this->assertOpen();

        if (!isset($this->steps[$this->step])) {
            return '';
        }

        $chunk = $this->steps[$this->step];
        ++$this->step;

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
     * @throws ConnectionException
     */
    private function assertOpen(): void
    {
        if (!$this->open) {
            throw new ConnectionException('Соединение закрыто');
        }
    }
}

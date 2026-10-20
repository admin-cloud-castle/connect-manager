<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Connection;

use CloudCastle\ConnectManager\Contract\ConnectionInterface;
use CloudCastle\ConnectManager\Exception\ConnectionException;

/**
 * Соединение на базе PHP stream resource.
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 * @SuppressWarnings("PHPMD.ErrorControlOperator")
 */
final class StreamConnection implements ConnectionInterface
{
    /**
     * @var resource|null
     */
    private $stream;

    private bool $open;

    /**
     * @param mixed                $stream   Ожидается stream resource
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        mixed $stream,
        private readonly string $protocol,
        private readonly int $osiLayer,
        private readonly ?string $remoteEndpoint,
        private readonly array $metadata = [],
    ) {
        if (!is_resource($stream)) {
            throw new ConnectionException('Ожидался stream resource');
        }

        $this->stream = $stream;
        $this->open = true;
        @stream_set_blocking($stream, true);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function protocol(): string
    {
        return $this->protocol;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function osiLayer(): int
    {
        return $this->osiLayer;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function isOpen(): bool
    {
        return $this->open && is_resource($this->stream);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function remoteEndpoint(): ?string
    {
        return $this->remoteEndpoint;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function write(string $payload): int
    {
        $stream = $this->requireStream();
        $written = fwrite($stream, $payload);

        if ($written === false) {
            throw new ConnectionException('Ошибка записи в соединение');
        }

        return $written;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function read(int $length): string
    {
        $stream = $this->requireStream();

        if ($length < 1) {
            throw new ConnectionException('Длина чтения должна быть ≥ 1');
        }

        $data = fread($stream, $length);

        if ($data === false) {
            throw new ConnectionException('Ошибка чтения из соединения');
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     *
     * @return \Generator<int, string>
     */
    #[\Override]
    public function readChunks(int $chunkSize = 8192): \Generator
    {
        $stream = $this->requireStream();

        if ($chunkSize < 1) {
            throw new ConnectionException('Размер чанка должен быть ≥ 1');
        }

        while ($this->isOpen() && !feof($stream)) {
            $chunk = fread($stream, $chunkSize);

            if ($chunk === false) {
                throw new ConnectionException('Ошибка чтения чанка');
            }

            if ($chunk === '') {
                break;
            }

            yield $chunk;
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function close(): void
    {
        $stream = $this->stream;
        $this->stream = null;
        $this->open = false;

        if (!is_resource($stream)) {
            return;
        }

        fclose($stream);
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function metadata(): array
    {
        $meta = $this->metadata;

        if (is_resource($this->stream)) {
            $meta['stream'] = stream_get_meta_data($this->stream);
        }

        return $meta;
    }

    /**
     * {@inheritdoc}
     *
     * @return resource|null
     */
    #[\Override]
    public function resource(): mixed
    {
        return $this->stream;
    }

    /**
     * Возвращает открытый stream или бросает исключение.
     *
     * @return resource
     *
     * @throws ConnectionException
     */
    private function requireStream()
    {
        if (!$this->open || !is_resource($this->stream)) {
            throw new ConnectionException('Соединение закрыто');
        }

        return $this->stream;
    }
}

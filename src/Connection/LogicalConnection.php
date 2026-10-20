<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Connection;

use CloudCastle\ConnectManager\Contract\ConnectionInterface;
use CloudCastle\ConnectManager\Exception\ConnectionException;

/**
 * Логическое соединение для протоколов без нативного PHP-транспорта.
 *
 * Возвращает объект соединения с метаданными протокола; I/O доступен после
 * привязки пользовательского ресурса через attach().
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
final class LogicalConnection implements ConnectionInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $metadata;

    /**
     * @var resource|null
     */
    private $attached;

    private bool $open = true;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private readonly string $protocol,
        private readonly int $osiLayer,
        private readonly ?string $remoteEndpoint,
        array $metadata = [],
    ) {
        $this->metadata = $metadata + ['logical' => true];
    }

    /**
     * Привязывает реальный stream к логическому соединению.
     *
     * @param mixed $stream Ожидается stream resource
     */
    public function attach(mixed $stream): void
    {
        if (!is_resource($stream)) {
            throw new ConnectionException('attach() ожидает stream resource');
        }

        $this->attached = $stream;
        $this->metadata['attached'] = true;
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
        return $this->open;
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
        $stream = $this->requireAttached();
        $written = fwrite($stream, $payload);

        if ($written === false) {
            throw new ConnectionException('Ошибка записи в логическое соединение');
        }

        return $written;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function read(int $length): string
    {
        $stream = $this->requireAttached();

        if ($length < 1) {
            throw new ConnectionException('Длина чтения должна быть ≥ 1');
        }

        $data = fread($stream, $length);

        if ($data === false) {
            throw new ConnectionException('Ошибка чтения из логического соединения');
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
        $stream = $this->requireAttached();

        if ($chunkSize < 1) {
            throw new ConnectionException('Размер чанка должен быть ≥ 1');
        }

        while (!feof($stream)) {
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
        $stream = $this->attached;
        $this->attached = null;
        $this->open = false;

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * {@inheritdoc}
     *
     * @return resource|null
     */
    #[\Override]
    public function resource(): mixed
    {
        return $this->attached;
    }

    /**
     * Возвращает привязанный stream.
     *
     * @return resource
     *
     * @throws ConnectionException
     */
    private function requireAttached()
    {
        if (!$this->open) {
            throw new ConnectionException('Соединение закрыто');
        }

        if (!is_resource($this->attached)) {
            throw new ConnectionException(
                'Логическое соединение без привязанного ресурса: используйте attach() или native-протокол',
            );
        }

        return $this->attached;
    }
}

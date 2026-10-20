<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Support;

use CloudCastle\ConnectManager\Contract\ConnectionInterface;

/**
 * Соединение-заглушка со счётчиком close() для мутационных тестов.
 *
 * @psalm-suppress PossiblyUnusedMethod
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
final class TrackableConnection implements ConnectionInterface
{
    private bool $open = true;

    /** @var callable(TrackableConnection): void|null */
    private $onClose;

    /**
     * @param callable(TrackableConnection): void|null $onClose
     */
    public function __construct(
        private readonly string $protocolId,
        private readonly ?string $endpoint,
        ?callable $onClose = null,
    ) {
        $this->onClose = $onClose;
    }

    /**
     * Помечает соединение мёртвым без вызова close().
     */
    public function markDead(): void
    {
        $this->open = false;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function protocol(): string
    {
        return $this->protocolId;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function osiLayer(): int
    {
        return 4;
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
        return $this->endpoint;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function write(string $payload): int
    {
        return strlen($payload);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function read(int $length): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     *
     * @return \Generator<int, string>
     */
    #[\Override]
    public function readChunks(int $chunkSize = 8192): \Generator
    {
        yield from [];
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function close(): void
    {
        $this->open = false;

        if ($this->onClose !== null) {
            ($this->onClose)($this);
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
}

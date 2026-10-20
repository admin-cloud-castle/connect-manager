<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Driver\Ssh;

use CloudCastle\ConnectManager\Contract\ConnectionInterface;
use CloudCastle\ConnectManager\Exception\ConnectionException;

/**
 * Прикладное SSH/SFTP соединение (нативная сессия) в контракте ConnectionInterface.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
final class SshConnection implements ConnectionInterface
{
    private bool $open = true;

    /**
     * @param array<string, mixed> $metadata Метаданные
     */
    public function __construct(
        private readonly SshSessionInterface $session,
        private readonly string $protocol,
        private readonly ?string $remoteEndpoint,
        private readonly array $metadata = [],
    ) {
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
        return 7;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function isOpen(): bool
    {
        return $this->open && $this->session->isConnected();
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
     *
     * @throws ConnectionException
     */
    #[\Override]
    public function write(string $payload): int
    {
        $this->assertOpen();
        $this->session->write($payload);

        return strlen($payload);
    }

    /**
     * {@inheritdoc}
     *
     * @throws ConnectionException
     */
    #[\Override]
    public function read(int $length): string
    {
        $this->assertOpen();

        return $this->session->read($length);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function readChunks(int $chunkSize = 8192): \Generator
    {
        $chunk = $this->read($chunkSize);

        if ($chunk !== '') {
            yield $chunk;
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function close(): void
    {
        if ($this->open) {
            $this->session->disconnect();
            $this->open = false;
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function resource(): mixed
    {
        return $this->session;
    }

    /**
     * Выполняет удалённую команду и возвращает stdout.
     *
     * @throws ConnectionException
     */
    public function exec(string $command): string
    {
        $this->assertOpen();

        return $this->session->exec($command);
    }

    /**
     * Открывает SFTP-клиент.
     *
     * @throws ConnectionException
     */
    public function sftp(): SftpClient
    {
        $this->assertOpen();

        return $this->session->openSftp();
    }

    /**
     * @throws ConnectionException
     */
    private function assertOpen(): void
    {
        if (!$this->isOpen()) {
            throw new ConnectionException('SSH-соединение закрыто');
        }
    }
}

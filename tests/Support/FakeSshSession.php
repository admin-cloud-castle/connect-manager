<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Support;

use CloudCastle\ConnectManager\Driver\Ssh\SftpChannelInterface;
use CloudCastle\ConnectManager\Driver\Ssh\SftpClient;
use CloudCastle\ConnectManager\Driver\Ssh\SshSessionInterface;
use CloudCastle\ConnectManager\Exception\ConnectionException;

/**
 * Fake SSH/SFTP сессия для unit-тестов без сети и без phpseclib.
 */
final class FakeSshSession implements SshSessionInterface, SftpChannelInterface
{
    private bool $connected = true;

    private string $inbox = '';

    /** @var list<string> */
    private array $sftpReplies = [];

    /**
     * @param string       $execOutput  Ответ exec()
     * @param list<string> $sftpReplies Очередь SFTP-ответов
     */
    public function __construct(
        private readonly string $execOutput = 'ok',
        array $sftpReplies = [],
    ) {
        $this->sftpReplies = $sftpReplies;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function disconnect(): void
    {
        $this->connected = false;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function exec(string $command): string
    {
        $this->assertOpen();

        return $this->execOutput . ':' . $command;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function write(string $payload): void
    {
        $this->assertOpen();
        $this->inbox .= $payload;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function read(int $maxLength = 8192): string
    {
        $this->assertOpen();

        if ($this->inbox === '') {
            return '';
        }

        $length = max(1, $maxLength);
        $chunk = substr($this->inbox, 0, $length);
        $this->inbox = substr($this->inbox, strlen($chunk));

        return $chunk;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function openSftp(): SftpClient
    {
        $this->assertOpen();

        return new SftpClient($this);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function sendSftp(string $sftpPayload): void
    {
        $this->assertOpen();
        $this->inbox .= $sftpPayload;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function receiveSftp(): string
    {
        $this->assertOpen();

        if ($this->sftpReplies === []) {
            throw new ConnectionException('Нет подготовленных SFTP-ответов');
        }

        return array_shift($this->sftpReplies);
    }

    /**
     * @throws ConnectionException
     */
    private function assertOpen(): void
    {
        if (!$this->connected) {
            throw new ConnectionException('SSH-соединение закрыто');
        }
    }
}

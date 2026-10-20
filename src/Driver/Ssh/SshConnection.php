<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Driver\Ssh;

use CloudCastle\ConnectManager\Contract\ConnectionInterface;
use CloudCastle\ConnectManager\Exception\ConnectionException;
use Generator;

/**
 * Прикладное SSH/SFTP соединение (нативная сессия) в контракте ConnectionInterface.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
final class SshConnection implements ConnectionInterface
{
    /**
     * Локальный флаг «соединение ещё не закрыто через close()».
     *
     * @var bool
     */
    private bool $open = true;

    /**
     * Оборачивает нативную SSH-сессию в ConnectionInterface.
     *
     * @param SshSessionInterface  $session        Аутентифицированная сессия
     * @param string               $protocol       Идентификатор протокола («ssh» или «sftp»)
     * @param string|null          $remoteEndpoint Исходный endpoint пользователя (host:port)
     * @param array<string, mixed> $metadata       Служебные метаданные (engine, pinned_ips, …)
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
     *
     * @return string Идентификатор протокола («ssh» / «sftp»)
     */
    #[\Override]
    public function protocol(): string
    {
        return $this->protocol;
    }

    /**
     * {@inheritdoc}
     *
     * @return int Всегда 7 (прикладной уровень OSI)
     */
    #[\Override]
    public function osiLayer(): int
    {
        return 7;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool true, пока не вызван close() и сессия isConnected()
     */
    #[\Override]
    public function isOpen(): bool
    {
        return $this->open && $this->session->isConnected();
    }

    /**
     * {@inheritdoc}
     *
     * @return string|null Endpoint из конструктора или null
     */
    #[\Override]
    public function remoteEndpoint(): ?string
    {
        return $this->remoteEndpoint;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $payload Байты в session-канал
     *
     * @return int Число записанных байт (strlen payload)
     *
     * @throws ConnectionException Если соединение закрыто
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
     * @param int $length Максимум байт за чтение
     *
     * @return string Прочитанный фрагмент или ''
     *
     * @throws ConnectionException Если соединение закрыто
     */
    #[\Override]
    public function read(int $length): string
    {
        $this->assertOpen();

        return $this->session->read($length);
    }

    /**
     * {@inheritdoc}
     *
     * @param int $chunkSize Размер порции для одного yield
     *
     * @return Generator<int, string, mixed, void> Не более одного чанка за итерацию
     *
     * @throws ConnectionException Если соединение закрыто
     */
    #[\Override]
    public function readChunks(int $chunkSize = 8192): Generator
    {
        $chunk = $this->read($chunkSize);

        if ($chunk !== '') {
            yield $chunk;
        }
    }

    /**
     * {@inheritdoc}
     *
     * Отправляет DISCONNECT и помечает соединение закрытым.
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
     *
     * @return array<string, mixed> Копия метаданных конструктора
     */
    #[\Override]
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * {@inheritdoc}
     *
     * @return SshSessionInterface Внутренняя нативная сессия
     */
    #[\Override]
    public function resource(): mixed
    {
        return $this->session;
    }

    /**
     * Выполняет удалённую команду и возвращает stdout (и накопленный stderr канала).
     *
     * @param string $command Команда для channel request «exec»
     *
     * @return string Вывод команды
     *
     * @throws ConnectionException Если соединение закрыто или протокол упал
     */
    public function exec(string $command): string
    {
        $this->assertOpen();

        return $this->session->exec($command);
    }

    /**
     * Открывает прикладной SFTP-клиент поверх той же сессии.
     *
     * @return SftpClient Клиент get/put/list/mkdir/delete
     *
     * @throws ConnectionException Если соединение закрыто или subsystem не поднялся
     */
    public function sftp(): SftpClient
    {
        $this->assertOpen();

        return $this->session->openSftp();
    }

    /**
     * Гарантирует, что соединение ещё открыто.
     *
     * @throws ConnectionException Если isOpen() === false
     */
    private function assertOpen(): void
    {
        if (!$this->isOpen()) {
            throw new ConnectionException('SSH-соединение закрыто');
        }
    }
}

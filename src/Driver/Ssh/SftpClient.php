<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Driver\Ssh;

use CloudCastle\ConnectManager\Exception\ConnectionException;
use phpseclib3\Net\SFTP;

/**
 * Прикладной SFTP-клиент поверх phpseclib.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
final class SftpClient
{
    /**
     * @param SFTP $sftp Активная SFTP-сессия
     */
    public function __construct(private readonly SFTP $sftp)
    {
    }

    /**
     * Читает удалённый файл.
     *
     * @throws ConnectionException
     */
    public function get(string $remotePath): string
    {
        $data = $this->sftp->get($remotePath);

        if ($data === false) {
            throw new ConnectionException('SFTP get не удался: ' . $remotePath);
        }

        return is_string($data) ? $data : '';
    }

    /**
     * Записывает удалённый файл.
     *
     * @throws ConnectionException
     */
    public function put(string $remotePath, string $contents): void
    {
        if ($this->sftp->put($remotePath, $contents) !== true) {
            throw new ConnectionException('SFTP put не удался: ' . $remotePath);
        }
    }

    /**
     * Список файлов каталога.
     *
     * @return list<string>
     *
     * @throws ConnectionException
     */
    public function list(string $remotePath = '.'): array
    {
        $list = $this->sftp->nlist($remotePath);

        if ($list === false) {
            throw new ConnectionException('SFTP list не удался: ' . $remotePath);
        }

        return array_values(array_filter(
            $list,
            static fn (mixed $entry): bool => is_string($entry),
        ));
    }

    /**
     * Создаёт каталог.
     *
     * @throws ConnectionException
     */
    public function mkdir(string $remotePath): void
    {
        if ($this->sftp->mkdir($remotePath) !== true) {
            throw new ConnectionException('SFTP mkdir не удался: ' . $remotePath);
        }
    }

    /**
     * Удаляет файл.
     *
     * @throws ConnectionException
     */
    public function delete(string $remotePath): void
    {
        if ($this->sftp->delete($remotePath) !== true) {
            throw new ConnectionException('SFTP delete не удался: ' . $remotePath);
        }
    }

    /**
     * Проверяет существование пути.
     */
    public function exists(string $remotePath): bool
    {
        return $this->sftp->file_exists($remotePath);
    }

    /**
     * Низкоуровневый SFTP.
     */
    public function resource(): SFTP
    {
        return $this->sftp;
    }
}

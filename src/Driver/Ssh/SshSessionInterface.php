<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Driver\Ssh;

use CloudCastle\ConnectManager\Exception\ConnectionException;

/**
 * Внутренний контракт прикладной SSH-сессии без внешних Composer-зависимостей.
 */
interface SshSessionInterface
{
    /**
     * Проверяет, что сессия ещё активна после handshake и аутентификации.
     *
     * @return bool true — можно вызывать exec/write/read/openSftp
     */
    public function isConnected(): bool;

    /**
     * Корректно отключает сессию (DISCONNECT) и освобождает транспорт.
     */
    public function disconnect(): void;

    /**
     * Выполняет команду на удалённом хосте через channel request «exec».
     *
     * @param string $command Команда shell без интерактивного TTY
     *
     * @return string stdout/stderr, накопленные до EOF канала
     *
     * @throws ConnectionException При закрытой сессии или ошибке протокола
     */
    public function exec(string $command): string;

    /**
     * Пишет данные в открытый session-канал (CHANNEL_DATA).
     *
     * @param string $payload Байты для удалённой стороны
     *
     * @throws ConnectionException При закрытой сессии или ошибке записи
     */
    public function write(string $payload): void;

    /**
     * Читает доступные данные из буфера session-канала.
     *
     * @param int $maxLength Максимум байт за вызов; значения ≤ 0 трактуются как 1
     *
     * @return string Прочитанный фрагмент или пустая строка, если данных нет
     *
     * @throws ConnectionException При закрытой сессии или ошибке чтения
     */
    public function read(int $maxLength = 8192): string;

    /**
     * Открывает SFTP subsystem поверх той же SSH-сессии.
     *
     * @return SftpClient Прикладной клиент get/put/list/mkdir/delete
     *
     * @throws ConnectionException Если subsystem не поднялся
     */
    public function openSftp(): SftpClient;
}

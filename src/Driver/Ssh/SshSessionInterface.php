<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Driver\Ssh;

/**
 * Внутренний контракт прикладной SSH-сессии (без внешних Composer-зависимостей).
 */
interface SshSessionInterface
{
    /**
     * Сессия активна.
     */
    public function isConnected(): bool;

    /**
     * Отключает сессию.
     */
    public function disconnect(): void;

    /**
     * Выполняет команду на удалённом хосте.
     */
    public function exec(string $command): string;

    /**
     * Пишет в интерактивный канал (если открыт) или в служебный буфер.
     */
    public function write(string $payload): void;

    /**
     * Читает доступные данные канала.
     */
    public function read(int $maxLength = 8192): string;

    /**
     * Открывает SFTP поверх той же SSH-сессии.
     */
    public function openSftp(): SftpClient;
}

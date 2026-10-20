<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Driver\Ssh;

/**
 * Канал обмена SFTP-PDU поверх SSH.
 */
interface SftpChannelInterface
{
    /**
     * Отправляет SFTP payload (без внешнего uint32 length framing — его добавляет клиент).
     */
    public function sendSftp(string $sftpPayload): void;

    /**
     * Читает один SFTP payload.
     */
    public function receiveSftp(): string;
}

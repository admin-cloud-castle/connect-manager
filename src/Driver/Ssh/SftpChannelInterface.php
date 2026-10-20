<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Driver\Ssh;

use CloudCastle\ConnectManager\Exception\ConnectionException;

/**
 * Канал обмена SFTP-PDU поверх уже установленной SSH-сессии.
 */
interface SftpChannelInterface
{
    /**
     * Отправляет SFTP payload без внешнего uint32 length framing (его добавляет клиент).
     *
     * @param string $sftpPayload Тело SFTP-пакета (тип + поля RFC 4254/4256)
     *
     * @throws ConnectionException При закрытом соединении или ошибке записи
     */
    public function sendSftp(string $sftpPayload): void;

    /**
     * Читает один полный SFTP payload (уже без внешнего length framing).
     *
     * @return string Тело пакета; пустая строка недопустима при успешном чтении
     *
     * @throws ConnectionException При закрытом соединении или ошибке чтения
     */
    public function receiveSftp(): string;
}

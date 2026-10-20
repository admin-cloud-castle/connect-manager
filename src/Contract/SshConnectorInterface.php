<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Contract;

/**
 * Контракт открытия прикладного SSH/SFTP.
 */
interface SshConnectorInterface
{
    /**
     * Открывает SSH или SFTP сессию как ConnectionInterface (SshConnection).
     */
    public function connect(
        string $protocolId,
        string $endpoint,
        ConnectionOptions $options,
    ): ConnectionInterface;
}

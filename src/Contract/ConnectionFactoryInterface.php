<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Contract;

/**
 * Фабрика соединений по протоколу и endpoint.
 */
interface ConnectionFactoryInterface
{
    /**
     * Создаёт и открывает соединение.
     *
     */
    public function connect(
        string $protocolId,
        string $endpoint,
        ?ConnectionOptions $options = null,
    ): ConnectionInterface;
}

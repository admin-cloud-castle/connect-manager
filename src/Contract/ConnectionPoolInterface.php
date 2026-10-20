<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Contract;

/**
 * Пул повторно используемых соединений.
 */
interface ConnectionPoolInterface
{
    /**
     * Берёт свободное соединение или создаёт новое.
     */
    public function acquire(
        string $protocolId,
        string $endpoint,
        ?ConnectionOptions $options = null,
    ): ConnectionInterface;

    /**
     * Возвращает соединение в пул (если ещё открыто).
     */
    public function release(ConnectionInterface $connection): void;

    /**
     * Закрывает все соединения пула.
     */
    public function drain(): void;

    /**
     * Текущий размер пула (открытые слоты).
     */
    public function size(): int;
}

<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Pool;

use CloudCastle\ConnectManager\Contract\ConnectionFactoryInterface;
use CloudCastle\ConnectManager\Contract\ConnectionInterface;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Contract\ConnectionPoolInterface;

/**
 * Простой пул соединений с ключом protocol|endpoint.
 */
final class ConnectionPool implements ConnectionPoolInterface
{
    private ConnectionFactoryInterface $factory;

    private int $maxPerKey;

    /**
     * @var array<string, list<ConnectionInterface>>
     */
    private array $idle = [];

    /**
     * @param ConnectionFactoryInterface $factory   Фабрика
     * @param int                        $maxPerKey Максимум idle на ключ
     */
    public function __construct(ConnectionFactoryInterface $factory, int $maxPerKey = 8)
    {
        $this->factory = $factory;
        $this->maxPerKey = $maxPerKey > 0 ? $maxPerKey : 8;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function acquire(
        string $protocolId,
        string $endpoint,
        ?ConnectionOptions $options = null,
    ): ConnectionInterface {
        $key = $this->key($protocolId, $endpoint);

        while (isset($this->idle[$key]) && $this->idle[$key] !== []) {
            $connection = array_pop($this->idle[$key]);

            if ($connection->isOpen()) {
                return $connection;
            }

            $connection->close();
        }

        return $this->factory->connect($protocolId, $endpoint, $options);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function release(ConnectionInterface $connection): void
    {
        if (!$connection->isOpen()) {
            return;
        }

        $endpoint = $connection->remoteEndpoint() ?? '';
        $key = $this->key($connection->protocol(), $endpoint);
        $bucket = $this->idle[$key] ?? [];

        if (count($bucket) >= $this->maxPerKey) {
            $connection->close();

            return;
        }

        $bucket[] = $connection;
        $this->idle[$key] = $bucket;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function drain(): void
    {
        foreach ($this->idle as $bucket) {
            foreach ($bucket as $connection) {
                $connection->close();
            }
        }

        $this->idle = [];
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function size(): int
    {
        $total = 0;

        foreach ($this->idle as $bucket) {
            $total += count($bucket);
        }

        return $total;
    }

    /**
     * Ключ пула.
     */
    private function key(string $protocolId, string $endpoint): string
    {
        return strtolower($protocolId) . '|' . $endpoint;
    }
}

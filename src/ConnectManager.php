<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager;

use CloudCastle\ConnectManager\Async\FiberAsyncRuntime;
use CloudCastle\ConnectManager\Contract\AsyncRuntimeInterface;
use CloudCastle\ConnectManager\Contract\ConnectionFactoryInterface;
use CloudCastle\ConnectManager\Contract\ConnectionInterface;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Contract\ConnectionPoolInterface;
use CloudCastle\ConnectManager\Contract\ConnectManagerInterface;
use CloudCastle\ConnectManager\Contract\ProtocolRegistryInterface;
use CloudCastle\ConnectManager\Driver\ConnectionFactory;
use CloudCastle\ConnectManager\Exception\ConnectManagerException;
use CloudCastle\ConnectManager\Pool\ConnectionPool;
use CloudCastle\ConnectManager\Protocol\ProtocolRegistry;

/**
 * Унифицированный менеджер сетевых соединений (sync + async).
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
final class ConnectManager implements ConnectManagerInterface
{
    /**
     * Версия API пакета (SemVer совместимости; релиз — через Packagist/git tag).
     */
    public const VERSION = '1.0.0';

    private readonly ProtocolRegistryInterface $registry;

    private readonly ConnectionFactoryInterface $factory;

    private readonly ConnectionPoolInterface $pool;

    private readonly AsyncRuntimeInterface $async;

    /**
     * @param ProtocolRegistryInterface|null  $registry Реестр протоколов
     * @param ConnectionFactoryInterface|null $factory  Фабрика соединений
     * @param ConnectionPoolInterface|null    $pool     Пул
     * @param AsyncRuntimeInterface|null      $async    Async-рантайм
     */
    public function __construct(
        ?ProtocolRegistryInterface $registry = null,
        ?ConnectionFactoryInterface $factory = null,
        ?ConnectionPoolInterface $pool = null,
        ?AsyncRuntimeInterface $async = null,
    ) {
        $this->registry = $registry ?? new ProtocolRegistry();
        $this->factory = $factory ?? new ConnectionFactory($this->registry);
        $this->pool = $pool ?? new ConnectionPool($this->factory);
        $this->async = $async ?? new FiberAsyncRuntime();
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function name(): string
    {
        return 'cloud-castle/connect-manager';
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function connect(
        string $protocolId,
        string $endpoint,
        ?ConnectionOptions $options = null,
    ): ConnectionInterface {
        return $this->factory->connect($protocolId, $endpoint, $options);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function connectAsync(
        string $protocolId,
        string $endpoint,
        callable $onConnected,
        ?ConnectionOptions $options = null,
    ): mixed {
        $options = ($options ?? ConnectionOptions::defaults())->withMode('async');

        return $this->async->run(function () use ($protocolId, $endpoint, $onConnected, $options): mixed {
            $connection = $this->factory->connect($protocolId, $endpoint, $options);

            try {
                return $onConnected($connection);
            } finally {
                if ($connection->isOpen()) {
                    $connection->close();
                }
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function protocols(): ProtocolRegistryInterface
    {
        return $this->registry;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function pool(): ConnectionPoolInterface
    {
        return $this->pool;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function async(): AsyncRuntimeInterface
    {
        return $this->async;
    }

    /**
     * Завершает работу с ошибкой пакета.
     *
     * @throws ConnectManagerException
     */
    public function abort(string $message): never
    {
        throw new ConnectManagerException($message);
    }
}

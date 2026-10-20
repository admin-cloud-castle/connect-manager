<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Contract;

/**
 * Публичный фасад Connect Manager.
 */
interface ConnectManagerInterface
{
    /**
     * Возвращает идентификатор пакета Composer.
     */
    public function name(): string;

    /**
     * Открывает соединение указанного протокола.
     */
    public function connect(
        string $protocolId,
        string $endpoint,
        ?ConnectionOptions $options = null,
    ): ConnectionInterface;

    /**
     * Открывает соединение асинхронно внутри Fiber-рантайма.
     *
     * @param callable(ConnectionInterface): mixed $onConnected
     */
    public function connectAsync(
        string $protocolId,
        string $endpoint,
        callable $onConnected,
        ?ConnectionOptions $options = null,
    ): mixed;

    /**
     * Возвращает реестр протоколов.
     */
    public function protocols(): ProtocolRegistryInterface;

    /**
     * Возвращает пул соединений.
     */
    public function pool(): ConnectionPoolInterface;

    /**
     * Возвращает асинхронный рантайм.
     */
    public function async(): AsyncRuntimeInterface;
}

<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager;

use CloudCastle\ConnectManager\Async\FiberAsyncRuntime;
use CloudCastle\ConnectManager\Contract\AsyncRuntimeInterface;
use CloudCastle\ConnectManager\Contract\AuthCredentials;
use CloudCastle\ConnectManager\Contract\ConnectionFactoryInterface;
use CloudCastle\ConnectManager\Contract\ConnectionInterface;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Contract\ConnectionPoolInterface;
use CloudCastle\ConnectManager\Contract\ConnectManagerInterface;
use CloudCastle\ConnectManager\Contract\ProtocolRegistryInterface;
use CloudCastle\ConnectManager\Driver\ConnectionFactory;
use CloudCastle\ConnectManager\Driver\Http\HttpClient;
use CloudCastle\ConnectManager\Driver\Http\HttpRequest;
use CloudCastle\ConnectManager\Driver\Http\HttpResponse;
use CloudCastle\ConnectManager\Driver\Ssh\SftpClient;
use CloudCastle\ConnectManager\Driver\Ssh\SshConnection;
use CloudCastle\ConnectManager\Exception\ConnectManagerException;
use CloudCastle\ConnectManager\Exception\ConnectionException;
use CloudCastle\ConnectManager\Pool\ConnectionPool;
use CloudCastle\ConnectManager\Protocol\ProtocolRegistry;

/**
 * Унифицированный менеджер сетевых соединений (sync + async + HTTP + SSH/SFTP).
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
final class ConnectManager implements ConnectManagerInterface
{
    /**
     * Версия API пакета (SemVer совместимости; релиз — через Packagist/git tag).
     */
    public const VERSION = '1.1.0';

    private readonly ProtocolRegistryInterface $registry;

    private readonly ConnectionFactoryInterface $factory;

    private readonly ConnectionPoolInterface $pool;

    private readonly AsyncRuntimeInterface $async;

    private readonly HttpClient $http;

    /**
     * @param ProtocolRegistryInterface|null  $registry Реестр протоколов
     * @param ConnectionFactoryInterface|null $factory  Фабрика соединений
     * @param ConnectionPoolInterface|null    $pool     Пул
     * @param AsyncRuntimeInterface|null      $async    Async-рантайм
     * @param HttpClient|null                 $http     HTTP-клиент
     */
    public function __construct(
        ?ProtocolRegistryInterface $registry = null,
        ?ConnectionFactoryInterface $factory = null,
        ?ConnectionPoolInterface $pool = null,
        ?AsyncRuntimeInterface $async = null,
        ?HttpClient $http = null,
    ) {
        $this->registry = $registry ?? new ProtocolRegistry();
        $this->async = $async ?? new FiberAsyncRuntime();
        $this->factory = $factory ?? new ConnectionFactory($this->registry, async: $this->async);
        $this->pool = $pool ?? new ConnectionPool($this->factory);
        $this->http = $http ?? new HttpClient($this->factory);
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
     *
     * Устанавливает соединение в non-blocking режиме (STREAM_CLIENT_ASYNC_CONNECT)
     * с опросом готовности через async-рантайм.
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
     * Прикладной HTTP-клиент (get/post/json/redirects).
     */
    public function http(): HttpClient
    {
        return $this->http;
    }

    /**
     * Выполняет HTTP-запрос по URL.
     */
    public function request(HttpRequest $request, ?ConnectionOptions $options = null): HttpResponse
    {
        return $this->http->send($request, $options);
    }

    /**
     * HTTP/1.1 обмен поверх уже открытого соединения.
     *
     * @param array<string, string> $headers
     */
    public function httpExchange(
        ConnectionInterface $connection,
        string $method,
        string $target,
        array $headers = [],
        string $body = '',
        string $hostHeader = '',
    ): string {
        return $this->http->exchange($connection, $method, $target, $headers, $body, $hostHeader);
    }

    /**
     * Открывает прикладное SSH-соединение.
     *
     * @throws ConnectionException
     */
    public function ssh(string $endpoint, AuthCredentials $auth, ?ConnectionOptions $options = null): SshConnection
    {
        $options = ($options ?? ConnectionOptions::defaults())->withAuth($auth);
        $connection = $this->factory->connect('ssh', $endpoint, $options);

        if (!$connection instanceof SshConnection) {
            throw new ConnectionException('Ожидался SshConnection');
        }

        return $connection;
    }

    /**
     * Открывает прикладное SFTP-соединение и возвращает клиент.
     *
     * @throws ConnectionException
     */
    public function sftp(string $endpoint, AuthCredentials $auth, ?ConnectionOptions $options = null): SftpClient
    {
        $options = ($options ?? ConnectionOptions::defaults())->withAuth($auth);
        $connection = $this->factory->connect('sftp', $endpoint, $options);

        if (!$connection instanceof SshConnection) {
            throw new ConnectionException('Ожидался SshConnection для SFTP');
        }

        return $connection->sftp();
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

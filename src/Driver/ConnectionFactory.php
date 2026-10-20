<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Driver;

use CloudCastle\ConnectManager\Connection\LogicalConnection;
use CloudCastle\ConnectManager\Connection\StreamConnection;
use CloudCastle\ConnectManager\Contract\ConnectionFactoryInterface;
use CloudCastle\ConnectManager\Contract\ConnectionInterface;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Contract\ProtocolRegistryInterface;
use CloudCastle\ConnectManager\Exception\ProtocolException;
use CloudCastle\ConnectManager\Security\EndpointGuard;

/**
 * Фабрика соединений: security → resolve → open / logical.
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
final class ConnectionFactory implements ConnectionFactoryInterface
{
    private ProtocolRegistryInterface $registry;

    private EndpointGuard $guard;

    private TransportResolver $resolver;

    private StreamOpener $opener;

    /**
     * @param ProtocolRegistryInterface $registry Реестр протоколов
     * @param EndpointGuard|null        $guard    SSRF-guard
     * @param TransportResolver|null    $resolver Резолвер URI
     * @param StreamOpener|null         $opener   Открыватель stream
     */
    public function __construct(
        ProtocolRegistryInterface $registry,
        ?EndpointGuard $guard = null,
        ?TransportResolver $resolver = null,
        ?StreamOpener $opener = null,
    ) {
        $this->registry = $registry;
        $this->guard = $guard !== null ? $guard : new EndpointGuard();
        $this->resolver = $resolver !== null ? $resolver : new TransportResolver();
        $this->opener = $opener !== null ? $opener : new StreamOpener();
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
        $options ??= ConnectionOptions::defaults();
        $protocol = $this->registry->get($protocolId);
        $this->guard->assertAllowed($endpoint, $options);

        try {
            $uri = $this->resolver->resolve($protocol, $endpoint);
        } catch (ProtocolException $exception) {
            return new LogicalConnection(
                $protocol->id(),
                $protocol->osiLayer(),
                $endpoint,
                [
                    'reason' => $exception->getMessage(),
                    'transport' => $protocol->transport(),
                    'native' => false,
                ],
            );
        }

        $stream = $this->opener->open($uri, $options);

        return new StreamConnection(
            $stream,
            $protocol->id(),
            $protocol->osiLayer(),
            $endpoint,
            [
                'uri' => $uri,
                'transport' => $protocol->transport(),
                'native' => true,
                'mode' => $options->mode(),
            ],
        );
    }
}

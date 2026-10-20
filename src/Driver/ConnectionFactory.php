<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Driver;

use CloudCastle\ConnectManager\Connection\LogicalConnection;
use CloudCastle\ConnectManager\Connection\StreamConnection;
use CloudCastle\ConnectManager\Contract\AsyncRuntimeInterface;
use CloudCastle\ConnectManager\Contract\ConnectionFactoryInterface;
use CloudCastle\ConnectManager\Contract\ConnectionInterface;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Contract\ProtocolRegistryInterface;
use CloudCastle\ConnectManager\Contract\SshConnectorInterface;
use CloudCastle\ConnectManager\Driver\Ssh\SshConnector;
use CloudCastle\ConnectManager\Exception\ProtocolException;
use CloudCastle\ConnectManager\Security\EndpointGuard;
use CloudCastle\ConnectManager\Security\PinnedEndpoint;

/**
 * Фабрика соединений: security (DNS-pin) → SSH/SFTP | stream | logical.
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final class ConnectionFactory implements ConnectionFactoryInterface
{
    private readonly EndpointGuard $guard;

    private readonly TransportResolver $resolver;

    private readonly StreamOpener $opener;

    private readonly SshConnectorInterface $ssh;

    /**
     * @param ProtocolRegistryInterface  $registry Реестр протоколов
     * @param EndpointGuard|null         $guard    SSRF-guard
     * @param TransportResolver|null     $resolver Резолвер URI
     * @param StreamOpener|null          $opener   Открыватель stream
     * @param AsyncRuntimeInterface|null $async    Рантайм для non-blocking connect
     * @param SshConnectorInterface|null $ssh      SSH/SFTP коннектор
     */
    public function __construct(
        private readonly ProtocolRegistryInterface $registry,
        ?EndpointGuard $guard = null,
        ?TransportResolver $resolver = null,
        ?StreamOpener $opener = null,
        private readonly ?AsyncRuntimeInterface $async = null,
        ?SshConnectorInterface $ssh = null,
    ) {
        $this->guard = $guard ?? new EndpointGuard();
        $this->resolver = $resolver ?? new TransportResolver();
        $this->opener = $opener ?? new StreamOpener();
        $this->ssh = $ssh ?? new SshConnector($this->guard);
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

        if ($protocolId === 'ssh' || $protocolId === 'sftp') {
            return $this->ssh->connect($protocolId, $endpoint, $options);
        }

        $pinned = $this->guard->prepare($endpoint, $options);
        $options = $this->applyTlsPeerName($options, $pinned);

        try {
            $uri = $this->resolver->resolve($protocol, $pinned->connectEndpoint());
        } catch (ProtocolException $exception) {
            return new LogicalConnection(
                $protocol->id(),
                $protocol->osiLayer(),
                $endpoint,
                [
                    'reason' => $exception->getMessage(),
                    'transport' => $protocol->transport(),
                    'native' => false,
                    'capability' => 'logical',
                ],
            );
        }

        $runtime = $options->mode() === 'async' ? $this->async : null;
        $stream = $this->opener->open($uri, $options, $runtime);
        $this->guard->assertConnectedPeer($stream, $options);

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
                'capability' => $this->capabilityLabel($protocol->id()),
                'pinned_ips' => $pinned->resolvedIps(),
            ],
        );
    }

    /**
     * Подставляет SNI/peer_name при IP-pin hostname.
     */
    private function applyTlsPeerName(ConnectionOptions $options, PinnedEndpoint $pinned): ConnectionOptions
    {
        $peerName = $pinned->tlsPeerName();

        if ($peerName === null) {
            return $options;
        }

        return $options->withTlsPeerName($peerName);
    }

    /**
     * Метка прикладных возможностей протокола.
     */
    private function capabilityLabel(string $protocolId): string
    {
        if ($protocolId === 'http' || $protocolId === 'https') {
            return 'http-application';
        }

        return 'transport';
    }
}

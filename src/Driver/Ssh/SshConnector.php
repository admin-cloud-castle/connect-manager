<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Driver\Ssh;

use CloudCastle\ConnectManager\Contract\AuthCredentials;
use CloudCastle\ConnectManager\Contract\ConnectionInterface;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Contract\SshConnectorInterface;
use CloudCastle\ConnectManager\Exception\ConnectionException;
use CloudCastle\ConnectManager\Security\EndpointGuard;
use CloudCastle\ConnectManager\Security\PinnedEndpoint;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;

/**
 * Устанавливает прикладное SSH/SFTP соединение через phpseclib.
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final class SshConnector implements SshConnectorInterface
{
    private readonly SshAuthApplier $authApplier;

    /**
     * @param EndpointGuard       $guard       SSRF-guard
     * @param SshAuthApplier|null $authApplier Аутентификация
     */
    public function __construct(
        private readonly EndpointGuard $guard = new EndpointGuard(),
        ?SshAuthApplier $authApplier = null,
    ) {
        $this->authApplier = $authApplier ?? new SshAuthApplier();
    }

    /**
     * {@inheritdoc}
     *
     * @throws ConnectionException
     */
    #[\Override]
    public function connect(
        string $protocolId,
        string $endpoint,
        ConnectionOptions $options,
    ): ConnectionInterface {
        $auth = $options->auth();

        if (!$auth instanceof AuthCredentials) {
            throw new ConnectionException('Для SSH/SFTP нужны AuthCredentials в ConnectionOptions');
        }

        $pinned = $this->guard->prepare($endpoint, $options);
        [$host, $port] = $this->hostPort($pinned, $endpoint);
        $timeout = (int) max(1, $options->connectTimeout());
        $session = $protocolId === 'sftp'
            ? new SFTP($host, $port, $timeout)
            : new SSH2($host, $port, $timeout);

        $this->authApplier->login($session, $auth);

        if (!$session->isConnected()) {
            throw new ConnectionException(sprintf('Не удалось установить %s к %s:%d', $protocolId, $host, $port));
        }

        return new SshConnection(
            $session,
            $protocolId,
            $endpoint,
            [
                'capability' => 'ssh-application',
                'native' => true,
                'transport' => 'tcp',
                'pinned_ips' => $pinned->resolvedIps(),
                'host' => $host,
                'port' => $port,
            ],
        );
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function hostPort(PinnedEndpoint $pinned, string $originalEndpoint): array
    {
        $target = $pinned->connectEndpoint();
        $host = $this->guard->extractHost($target) ?? $this->guard->extractHost($originalEndpoint);

        if ($host === null || $host === '') {
            throw new ConnectionException('Не удалось извлечь host для SSH: ' . $originalEndpoint);
        }

        $port = 22;
        $parts = parse_url(str_contains($target, '://') ? $target : 'tcp://' . $target);

        if (is_array($parts) && isset($parts['port'])) {
            $port = $parts['port'];
        } elseif (preg_match('/:(\d+)$/', $target, $matches) === 1) {
            $port = (int) $matches[1];
        }

        return [$host, $port];
    }
}

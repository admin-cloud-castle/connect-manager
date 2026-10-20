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

/**
 * Устанавливает прикладное SSH/SFTP через нативный NativeSshSession (без Composer-зависимостей).
 *
 * @SuppressWarnings("PHPMD.ErrorControlOperator")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final class SshConnector implements SshConnectorInterface
{
    /**
     * @param EndpointGuard $guard SSRF-guard
     */
    public function __construct(
        private readonly EndpointGuard $guard = new EndpointGuard(),
    ) {
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
        $remote = $host . ':' . $port;
        $errno = 0;
        $errstr = '';
        $stream = @stream_socket_client(
            'tcp://' . (str_contains($host, ':') ? '[' . $host . ']' : $host) . ':' . $port,
            $errno,
            $errstr,
            (float) $timeout,
        );

        if ($stream === false) {
            throw new ConnectionException(sprintf('TCP к %s не удался: [%d] %s', $remote, $errno, $errstr));
        }

        stream_set_timeout($stream, $timeout);
        $session = new NativeSshSession($stream, $auth, $protocolId === 'sftp');

        return new SshConnection(
            $session,
            $protocolId,
            $endpoint,
            [
                'capability' => 'ssh-application',
                'native' => true,
                'transport' => 'tcp',
                'engine' => 'native-php',
                'pinned_ips' => $pinned->resolvedIps(),
                'host' => $host,
                'port' => $port,
            ],
        );
    }

    /**
     * @return array{0: string, 1: int}
     *
     * @throws ConnectionException
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

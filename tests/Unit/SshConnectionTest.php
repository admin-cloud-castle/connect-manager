<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\ConnectManager;
use CloudCastle\ConnectManager\Contract\AuthCredentials;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Contract\SshConnectorInterface;
use CloudCastle\ConnectManager\Driver\ConnectionFactory;
use CloudCastle\ConnectManager\Driver\Ssh\SftpClient;
use CloudCastle\ConnectManager\Driver\Ssh\SshConnection;
use CloudCastle\ConnectManager\Exception\ConnectionException;
use CloudCastle\ConnectManager\Protocol\ProtocolRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;

/**
 * Прикладной SSH/SFTP слой.
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.UnusedFormalParameter")
 */
#[CoversClass(SshConnection::class)]
#[CoversClass(SftpClient::class)]
#[CoversClass(AuthCredentials::class)]
final class SshConnectionTest extends TestCase
{
    /**
     * exec и write/read через mock SSH2.
     */
    public function testExecAndIo(): void
    {
        $ssh = $this->createMock(SSH2::class);
        $ssh->method('isConnected')->willReturn(true);
        $ssh->method('exec')->with('id')->willReturn('uid=0');
        $ssh->method('read')->willReturn('data');
        $ssh->expects(self::once())->method('disconnect');

        $connection = new SshConnection($ssh, 'ssh', 'host:22', ['capability' => 'ssh-application']);
        self::assertSame('ssh', $connection->protocol());
        self::assertSame('uid=0', $connection->exec('id'));
        self::assertSame(4, $connection->write('ping'));
        self::assertSame('data', $connection->read(4));
        $connection->close();
        self::assertFalse($connection->isOpen());
    }

    /**
     * SFTP клиент на mock SFTP.
     */
    public function testSftpClient(): void
    {
        $sftp = $this->createMock(SFTP::class);
        $sftp->method('isConnected')->willReturn(true);
        $sftp->method('get')->with('/a')->willReturn('body');
        $sftp->method('put')->willReturn(true);
        $sftp->method('nlist')->willReturn(['.', '..', 'a']);
        $sftp->method('mkdir')->willReturn(true);
        $sftp->method('delete')->willReturn(true);
        $sftp->method('file_exists')->willReturn(true);

        $connection = new SshConnection($sftp, 'sftp', 'host:22');
        $client = $connection->sftp();
        self::assertSame('body', $client->get('/a'));
        $client->put('/a', 'x');
        self::assertSame(['.', '..', 'a'], $client->list('.'));
        $client->mkdir('/d');
        $client->delete('/a');
        self::assertTrue($client->exists('/a'));
    }

    /**
     * sftp() на чистом SSH2 запрещён.
     */
    public function testSftpRequiresSftpSession(): void
    {
        $ssh = $this->createMock(SSH2::class);
        $ssh->method('isConnected')->willReturn(true);
        $connection = new SshConnection($ssh, 'ssh', 'h:22');

        $this->expectException(ConnectionException::class);
        $connection->sftp();
    }

    /**
     * Фабрика делегирует ssh коннектору.
     */
    public function testFactoryDelegatesToSshConnector(): void
    {
        $ssh = $this->createMock(SSH2::class);
        $ssh->method('isConnected')->willReturn(true);
        $expected = new SshConnection($ssh, 'ssh', '127.0.0.1:22');

        $connector = new class ($expected) implements SshConnectorInterface {
            public function __construct(private SshConnection $connection)
            {
            }

            #[\Override]
            public function connect(
                string $protocolId,
                string $endpoint,
                ConnectionOptions $options,
            ): \CloudCastle\ConnectManager\Contract\ConnectionInterface {
                return $this->connection;
            }
        };

        $factory = new ConnectionFactory(new ProtocolRegistry(), ssh: $connector);
        $options = ConnectionOptions::defaults()
            ->allowPrivateAddresses()
            ->withAuth(AuthCredentials::withPassword('u', 'p'));
        $connection = $factory->connect('ssh', '127.0.0.1:22', $options);

        self::assertSame($expected, $connection);

        $keyAuth = AuthCredentials::withPublicKey('u', "-----BEGIN FAKE-----\nX\n-----END FAKE-----");
        self::assertSame('u', $keyAuth->username());
        self::assertNull($keyAuth->password());
        self::assertNotNull($keyAuth->privateKey());

        $manager = new ConnectManager(factory: $factory);
        $ssh = $manager->ssh('127.0.0.1:22', AuthCredentials::withPassword('u', 'p'), $options);
        self::assertSame($expected, $ssh);

        $sftpSession = $this->createMock(SFTP::class);
        $sftpSession->method('isConnected')->willReturn(true);
        $sftpConn = new SshConnection($sftpSession, 'sftp', '127.0.0.1:22');
        $sftpConnector = new class ($sftpConn) implements SshConnectorInterface {
            public function __construct(private SshConnection $connection)
            {
            }

            #[\Override]
            public function connect(
                string $protocolId,
                string $endpoint,
                ConnectionOptions $options,
            ): \CloudCastle\ConnectManager\Contract\ConnectionInterface {
                return $this->connection;
            }
        };
        $sftpFactory = new ConnectionFactory(new ProtocolRegistry(), ssh: $sftpConnector);
        $sftpManager = new ConnectManager(factory: $sftpFactory);
        $sftpClient = $sftpManager->sftp('127.0.0.1:22', AuthCredentials::withPassword('u', 'p'), $options);
        self::assertSame($sftpSession, $sftpClient->resource());
    }
}

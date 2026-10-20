<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\ConnectManager;
use CloudCastle\ConnectManager\Contract\AuthCredentials;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Contract\SshConnectorInterface;
use CloudCastle\ConnectManager\Driver\ConnectionFactory;
use CloudCastle\ConnectManager\Driver\Ssh\SftpClient;
use CloudCastle\ConnectManager\Driver\Ssh\SshBinary;
use CloudCastle\ConnectManager\Driver\Ssh\SshConnection;
use CloudCastle\ConnectManager\Exception\ConnectionException;
use CloudCastle\ConnectManager\Protocol\ProtocolRegistry;
use CloudCastle\ConnectManager\Tests\Support\FakeSshSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Прикладной SSH/SFTP слой (нативный, без phpseclib в runtime).
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.UnusedFormalParameter")
 */
#[CoversClass(SshConnection::class)]
#[CoversClass(SftpClient::class)]
#[CoversClass(AuthCredentials::class)]
#[CoversClass(SshBinary::class)]
final class SshConnectionTest extends TestCase
{
    /**
     * exec / write / read через FakeSshSession.
     */
    public function testExecAndIo(): void
    {
        $session = new FakeSshSession('uid=0');
        $connection = new SshConnection($session, 'ssh', 'host:22', ['capability' => 'ssh-application']);

        self::assertSame('ssh', $connection->protocol());
        self::assertSame('uid=0:id', $connection->exec('id'));
        self::assertSame(4, $connection->write('ping'));
        self::assertSame('ping', $connection->read(4));
        $connection->close();
        self::assertFalse($connection->isOpen());
    }

    /**
     * SFTP get через подготовленные ответы.
     */
    public function testSftpGet(): void
    {
        $handle = 'H1';
        $open = chr(102) . SshBinary::uint32(1) . SshBinary::sshString($handle);
        $data = chr(103) . SshBinary::uint32(2) . SshBinary::sshString('body');
        $eof = chr(101) . SshBinary::uint32(3) . SshBinary::uint32(1) . SshBinary::sshString('') . SshBinary::sshString('');
        $close = chr(101) . SshBinary::uint32(4) . SshBinary::uint32(0) . SshBinary::sshString('') . SshBinary::sshString('');
        $session = new FakeSshSession('x', [$open, $data, $eof, $close]);
        $client = $session->openSftp();

        self::assertSame('body', $client->get('/a'));
        self::assertSame($session, $client->resource());

        $putOpen = chr(102) . SshBinary::uint32(1) . SshBinary::sshString('H2');
        $putOk = chr(101) . SshBinary::uint32(2) . SshBinary::uint32(0) . SshBinary::sshString('') . SshBinary::sshString('');
        $putClose = chr(101) . SshBinary::uint32(3) . SshBinary::uint32(0) . SshBinary::sshString('') . SshBinary::sshString('');
        $putSession = new FakeSshSession('x', [$putOpen, $putOk, $putClose]);
        $putSession->openSftp()->put('/b', 'xy');

        $mkdirOk = chr(101) . SshBinary::uint32(1) . SshBinary::uint32(0) . SshBinary::sshString('') . SshBinary::sshString('');
        $delOk = chr(101) . SshBinary::uint32(1) . SshBinary::uint32(0) . SshBinary::sshString('') . SshBinary::sshString('');
        (new FakeSshSession('x', [$mkdirOk]))->openSftp()->mkdir('/d');
        (new FakeSshSession('x', [$delOk]))->openSftp()->delete('/a');

        $attrs = chr(105) . SshBinary::uint32(1) . SshBinary::uint32(0);
        self::assertTrue((new FakeSshSession('x', [$attrs]))->openSftp()->exists('/a'));

        $dirHandle = 'D1';
        $dirOpen = chr(102) . SshBinary::uint32(1) . SshBinary::sshString($dirHandle);
        $dirName = chr(104)
            . SshBinary::uint32(2)
            . SshBinary::uint32(1)
            . SshBinary::sshString('file.txt')
            . SshBinary::sshString('-rw-r--r-- file.txt')
            . SshBinary::uint32(0)
            . str_repeat("\x00", 40);
        $dirEof = chr(101) . SshBinary::uint32(3) . SshBinary::uint32(1) . SshBinary::sshString('') . SshBinary::sshString('');
        $dirClose = chr(101) . SshBinary::uint32(4) . SshBinary::uint32(0) . SshBinary::sshString('') . SshBinary::sshString('');
        self::assertSame(
            ['file.txt'],
            (new FakeSshSession('x', [$dirOpen, $dirName, $dirEof, $dirClose]))->openSftp()->list('/'),
        );
    }

    /**
     * Закрытая сессия бросает.
     */
    public function testClosedThrows(): void
    {
        $session = new FakeSshSession();
        $connection = new SshConnection($session, 'ssh', 'h:22');
        $connection->close();

        $this->expectException(ConnectionException::class);
        $connection->exec('x');
    }

    /**
     * Фабрика делегирует ssh коннектору.
     */
    public function testFactoryDelegatesToSshConnector(): void
    {
        $expected = new SshConnection(new FakeSshSession(), 'ssh', '127.0.0.1:22');
        $connector = new class ($expected) implements SshConnectorInterface {
            public function __construct(private readonly SshConnection $connection)
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
        self::assertSame($expected, $manager->ssh('127.0.0.1:22', AuthCredentials::withPassword('u', 'p'), $options));

        $sftpConn = new SshConnection(new FakeSshSession(), 'sftp', '127.0.0.1:22');
        $sftpConnector = new class ($sftpConn) implements SshConnectorInterface {
            public function __construct(private readonly SshConnection $connection)
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
        $sftpManager = new ConnectManager(factory: new ConnectionFactory(new ProtocolRegistry(), ssh: $sftpConnector));
        $sftpClient = $sftpManager->sftp('127.0.0.1:22', AuthCredentials::withPassword('u', 'p'), $options);
        self::assertSame(FakeSshSession::class, $sftpClient->resource()::class);
    }
}

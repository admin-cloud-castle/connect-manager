<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Comparison;

use Amp\Socket\ConnectContext;
use Amp\Socket\DnsSocketConnector;
use CloudCastle\ConnectManager\ConnectManager;
use CloudCastle\ConnectManager\Contract\AuthCredentials;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Protocol\ProtocolRegistry;
use GuzzleHttp\Client;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use React\Socket\Connector;
use Socket\Raw\Factory;

/**
 * Поверхности API каждого аналога сопоставимы с нашими слоями (TCP/HTTP/SSH).
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
#[Group('comparison')]
#[CoversNothing]
final class CompetitorApiSurfaceTest extends TestCase
{
    /**
     * Connect Manager: registry + sync connect API.
     */
    public function testOursApi(): void
    {
        $manager = new ConnectManager();
        $methods = get_class_methods($manager);
        self::assertContains('connect', $methods);
        self::assertContains('http', $methods);
        self::assertContains('ssh', $methods);
        self::assertContains('sftp', $methods);
        self::assertSame('tcp', (new ProtocolRegistry())->get('tcp')->id());
        self::assertSame('u', AuthCredentials::withPassword('u', 'p')->username());
        self::assertTrue(ConnectionOptions::defaults()->verifyPeer());
    }

    /**
     * react/socket: Connector для TCP/TLS.
     */
    public function testReactSocketApi(): void
    {
        $connector = new Connector();
        self::assertContains('connect', get_class_methods($connector));
    }

    /**
     * amphp/socket: DnsSocketConnector + ConnectContext.
     */
    public function testAmphpSocketApi(): void
    {
        $connector = new DnsSocketConnector();
        self::assertContains('connect', get_class_methods($connector));
        $context = new ConnectContext();
        $contextMethods = get_class_methods($context);
        self::assertContains('withTcpNoDelay', $contextMethods);
        self::assertContains('withTlsContext', $contextMethods);
    }

    /**
     * guzzlehttp/guzzle: HTTP-клиент request/get/post.
     */
    public function testGuzzleApi(): void
    {
        $client = new Client(['http_errors' => false]);
        $methods = get_class_methods($client);

        foreach (['request', 'get', 'post', 'send'] as $method) {
            self::assertContains($method, $methods);
        }
    }

    /**
     * phpseclib: SSH2/SFTP login/exec/get/put.
     */
    public function testPhpSecLibApi(): void
    {
        self::assertContains('login', get_class_methods(SSH2::class));
        self::assertContains('exec', get_class_methods(SSH2::class));
        self::assertContains('get', get_class_methods(SFTP::class));
        self::assertContains('put', get_class_methods(SFTP::class));
        self::assertContains('nlist', get_class_methods(SFTP::class));
    }

    /**
     * clue/socket-raw: Factory createClient/createServer.
     */
    public function testSocketRawApi(): void
    {
        $factory = new Factory();
        $methods = get_class_methods($factory);
        self::assertContains('createClient', $methods);
        self::assertContains('createServer', $methods);
        self::assertContains('createFromString', $methods);
    }
}

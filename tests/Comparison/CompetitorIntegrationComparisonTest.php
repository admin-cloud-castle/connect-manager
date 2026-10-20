<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Comparison;

use CloudCastle\ConnectManager\ConnectManager;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use React\Socket\ConnectionInterface as ReactConnection;
use React\Socket\Connector;
use Socket\Raw\Factory;

/**
 * Интеграционные сравнения на localhost / mock transport (без внешних хостов).
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.ErrorControlOperator")
 */
#[Group('comparison')]
#[Group('integration')]
#[CoversNothing]
final class CompetitorIntegrationComparisonTest extends TestCase
{
    /**
     * Connect Manager и socket-raw читают/пишут один TCP echo на 127.0.0.1.
     */
    public function testLocalTcpEchoOursAndSocketRaw(): void
    {
        $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        if ($server === false) {
            self::markTestSkipped('stream_socket_server недоступен: ' . $errstr);
        }

        $name = stream_socket_get_name($server, false);
        self::assertIsString($name);
        $colon = strrpos($name, ':');
        self::assertNotFalse($colon);
        $port = (int) substr($name, $colon + 1);
        self::assertGreaterThan(0, $port);

        $payload = "ping-cmp\n";

        // Наш клиент
        $manager = new ConnectManager();
        $options = (new ConnectionOptions(2.0, 2.0))->allowPrivateAddresses();
        $ours = $manager->connect('tcp', '127.0.0.1:' . $port, $options);
        $peer = @stream_socket_accept($server, 2.0);
        self::assertNotFalse($peer);
        $ours->write($payload);
        $fromOurs = fread($peer, 64);
        self::assertSame($payload, $fromOurs);
        fwrite($peer, 'pong-ours');
        stream_set_timeout($peer, 1);
        self::assertSame('pong-ours', $ours->read(64));
        $ours->close();
        fclose($peer);

        // clue/socket-raw
        $factory = new Factory();
        $raw = $factory->createClient('127.0.0.1:' . $port);
        $peer2 = @stream_socket_accept($server, 2.0);
        self::assertNotFalse($peer2);
        $raw->write($payload);
        self::assertSame($payload, fread($peer2, 64));
        fwrite($peer2, 'pong-raw');
        self::assertSame('pong-raw', $raw->read(64));
        $raw->close();
        fclose($peer2);
        fclose($server);
    }

    /**
     * Guzzle выполняет HTTP exchange через MockHandler (без сети).
     */
    public function testGuzzleMockHttpExchange(): void
    {
        $mock = new MockHandler([new Response(200, ['X-Cmp' => '1'], 'guzzle-ok')]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $response = $client->get('http://127.0.0.1/mock');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('guzzle-ok', (string) $response->getBody());
    }

    /**
     * React Connector устанавливает TCP к локальному echo.
     */
    public function testReactLocalTcpConnect(): void
    {
        $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        if ($server === false) {
            self::markTestSkipped('stream_socket_server недоступен: ' . $errstr);
        }

        $name = stream_socket_get_name($server, false);
        self::assertIsString($name);
        $colon = strrpos($name, ':');
        self::assertNotFalse($colon);
        $port = (int) substr($name, $colon + 1);
        $connected = false;
        $connector = new Connector(['timeout' => 2]);
        $connector->connect('127.0.0.1:' . $port)->then(
            static function (ReactConnection $connection) use (&$connected): void {
                $connected = true;
                $connection->close();
            },
            static function (\Throwable $reason): void {
                self::fail('React connect failed: ' . $reason->getMessage());
            },
        );

        $peer = @stream_socket_accept($server, 2.0);
        self::assertNotFalse($peer);
        Loop::get()->addTimer(0.05, static function (): void {
            Loop::stop();
        });
        Loop::run();
        self::assertTrue($connected);
        fclose($peer);
        fclose($server);
    }
}

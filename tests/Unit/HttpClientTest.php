<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\ConnectManager;
use CloudCastle\ConnectManager\Contract\AuthCredentials;
use CloudCastle\ConnectManager\Contract\ConnectionFactoryInterface;
use CloudCastle\ConnectManager\Contract\ConnectionInterface;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Driver\Http\HttpClient;
use CloudCastle\ConnectManager\Driver\Http\HttpRequest;
use CloudCastle\ConnectManager\Driver\Http\HttpResponse;
use CloudCastle\ConnectManager\Exception\ConnectionException;
use CloudCastle\ConnectManager\Tests\Support\BufferConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Прикладной HTTP-клиент.
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.UnusedFormalParameter")
 */
#[CoversClass(HttpClient::class)]
#[CoversClass(HttpRequest::class)]
#[CoversClass(HttpResponse::class)]
#[CoversClass(ConnectManager::class)]
#[UsesClass(\CloudCastle\ConnectManager\Async\FiberAsyncRuntime::class)]
#[UsesClass(\CloudCastle\ConnectManager\Contract\AuthCredentials::class)]
#[UsesClass(\CloudCastle\ConnectManager\Contract\ConnectionOptions::class)]
#[UsesClass(\CloudCastle\ConnectManager\Pool\ConnectionPool::class)]
#[UsesClass(\CloudCastle\ConnectManager\Protocol\ProtocolCatalog::class)]
#[UsesClass(\CloudCastle\ConnectManager\Protocol\ProtocolDefinition::class)]
#[UsesClass(\CloudCastle\ConnectManager\Protocol\ProtocolRegistry::class)]
final class HttpClientTest extends TestCase
{
    /**
     * exchange собирает запрос и читает ответ с Content-Length.
     */
    public function testExchangeParsesResponse(): void
    {
        $connection = new BufferConnection(
            "HTTP/1.1 200 OK\r\nContent-Length: 5\r\nConnection: close\r\n\r\nhello",
        );
        $client = new HttpClient();
        $raw = $client->exchange($connection, 'GET', '/path', [], '', 'example.com');
        $response = HttpResponse::fromRaw($raw);

        self::assertSame(200, $response->statusCode());
        self::assertSame('OK', $response->reasonPhrase());
        self::assertSame('hello', $response->body());
        self::assertSame($raw, $response->raw());
        self::assertArrayHasKey('content-length', $response->headers());
        self::assertTrue($response->isSuccess());
        self::assertStringContainsString('GET /path HTTP/1.1', $connection->written());
        self::assertStringContainsString('Host: example.com', $connection->written());
    }

    /**
     * JSON helper и разбор тела.
     */
    public function testJsonRequestAndResponse(): void
    {
        $request = HttpRequest::json('POST', 'https://api.example/v1', ['a' => 1]);
        self::assertSame('POST', $request->method());
        self::assertSame('application/json', $request->headers()['Content-Type']);
        self::assertSame('{"a":1}', $request->body());
        self::assertSame(3, $request->withMaxRedirects(3)->maxRedirects());

        $response = HttpResponse::fromRaw(
            "HTTP/1.1 201 Created\r\nContent-Length: 11\r\n\r\n{\"ok\":true}",
        );
        self::assertSame(201, $response->statusCode());
        self::assertSame(['ok' => true], $response->json());
    }

    /**
     * Form request.
     */
    public function testFormRequest(): void
    {
        $request = HttpRequest::form('https://example.com/login', ['user' => 'a', 'pass' => 'b']);
        self::assertSame('application/x-www-form-urlencoded', $request->headers()['Content-Type']);
        self::assertStringContainsString('user=a', $request->body());
    }

    /**
     * send/get/post/put/patch/delete через fake factory + redirects.
     */
    public function testSendMethodsAndRedirect(): void
    {
        $factory = new class () implements ConnectionFactoryInterface {
            public int $calls = 0;

            #[\Override]
            public function connect(
                string $protocolId,
                string $endpoint,
                ?ConnectionOptions $options = null,
            ): ConnectionInterface {
                ++$this->calls;

                if ($this->calls === 1) {
                    return new BufferConnection(
                        "HTTP/1.1 302 Found\r\nLocation: /next\r\nContent-Length: 0\r\n\r\n",
                    );
                }

                return new BufferConnection(
                    "HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nok",
                );
            }
        };

        $client = new HttpClient($factory);
        $manager = new ConnectManager(factory: $factory, http: $client);

        self::assertSame($client, $manager->http());
        $get = $client->get('http://example.com/start', [], ['q' => 1]);
        self::assertSame(200, $get->statusCode());
        self::assertSame('ok', $get->body());
        self::assertSame(200, $client->post('http://example.com/', 'body')->statusCode());
        self::assertSame(200, $client->put('http://example.com/', 'body')->statusCode());
        self::assertSame(200, $client->patch('http://example.com/', 'body')->statusCode());
        self::assertSame(200, $client->delete('http://example.com/')->statusCode());
        self::assertSame(200, $client->postJson('http://example.com/', ['x' => 1])->statusCode());
        self::assertSame(200, $client->postForm('http://example.com/', ['a' => 'b'])->statusCode());

        $auth = AuthCredentials::withPassword('u', 'p');
        $options = ConnectionOptions::defaults()->withAuth($auth)->allowPrivateAddresses();
        $response = $manager->request(HttpRequest::get('http://example.com/'), $options);
        self::assertSame(200, $response->statusCode());
        $raw = $manager->httpExchange(
            new BufferConnection("HTTP/1.1 200 OK\r\nContent-Length: 3\r\n\r\nraw"),
            'GET',
            '/',
        );
        self::assertSame('raw', HttpResponse::fromRaw($raw)->body());
    }

    /**
     * Пустой ответ — ошибка.
     */
    public function testEmptyResponseThrows(): void
    {
        $client = new HttpClient();
        $connection = new BufferConnection('');
        $connection->close();

        $this->expectException(ConnectionException::class);
        $client->exchange($connection, 'GET', '/');
    }

    /**
     * Некорректный raw ответ.
     */
    public function testInvalidRawThrows(): void
    {
        $this->expectException(ConnectionException::class);
        HttpResponse::fromRaw("not-http\r\n\r\n");
    }
}

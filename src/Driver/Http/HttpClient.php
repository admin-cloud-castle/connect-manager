<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Driver\Http;

use CloudCastle\ConnectManager\Contract\AuthCredentials;
use CloudCastle\ConnectManager\Contract\ConnectionFactoryInterface;
use CloudCastle\ConnectManager\Contract\ConnectionInterface;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Exception\ConnectionException;

/**
 * Прикладной HTTP/1.1 клиент (URL, JSON, form, redirects, Basic Auth) поверх Connection API.
 *
 * @SuppressWarnings("PHPMD.CyclomaticComplexity")
 * @SuppressWarnings("PHPMD.NPathComplexity")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
final class HttpClient
{
    /**
     * @param ConnectionFactoryInterface|null $factory Фабрика для открытия http/https
     */
    public function __construct(
        private readonly ?ConnectionFactoryInterface $factory = null,
    ) {
    }

    /**
     * Выполняет запрос по URL с редиректами (нужна фабрика соединений).
     *
     * @throws ConnectionException
     */
    public function send(HttpRequest $request, ?ConnectionOptions $options = null): HttpResponse
    {
        if (!$this->factory instanceof ConnectionFactoryInterface) {
            throw new ConnectionException('HttpClient без ConnectionFactory: используйте exchange() или передайте factory');
        }

        $options ??= ConnectionOptions::defaults();
        $url = $this->applyQuery($request->url(), $request->query());
        $redirectsLeft = $request->maxRedirects();
        $method = $request->method();
        $body = $request->body();
        $headers = $request->headers();

        while (true) {
            $parts = $this->parseUrl($url);
            $protocol = $parts['scheme'] === 'https' ? 'https' : 'http';
            $endpoint = $parts['host'] . ':' . $parts['port'];
            $target = $parts['path'] . $parts['query'];
            $headers = $this->withHostAndAuth($headers, $parts['host'], $parts['port'], $options->auth());
            $connection = $this->factory->connect($protocol, $endpoint, $options);

            try {
                $raw = $this->exchange($connection, $method, $target, $headers, $body, $parts['host']);
            } finally {
                $connection->close();
            }

            $response = HttpResponse::fromRaw($raw);

            if (!$response->isRedirect() || $redirectsLeft < 1) {
                return $response;
            }

            $location = $response->header('location');

            if ($location === null || $location === '') {
                return $response;
            }

            $url = $this->resolveRedirect($url, $location);
            --$redirectsLeft;

            if (in_array($response->statusCode(), [301, 302, 303], true)) {
                $method = 'GET';
                $body = '';
            }
        }
    }

    /**
     * GET по URL.
     *
     * @param array<string, string> $headers
     * @param array<string, scalar|null> $query
     */
    public function get(string $url, array $headers = [], array $query = [], ?ConnectionOptions $options = null): HttpResponse
    {
        return $this->send(HttpRequest::get($url, $headers, $query), $options);
    }

    /**
     * POST по URL.
     *
     * @param array<string, string> $headers
     */
    public function post(string $url, string $body = '', array $headers = [], ?ConnectionOptions $options = null): HttpResponse
    {
        return $this->send(HttpRequest::post($url, $body, $headers), $options);
    }

    /**
     * PUT по URL.
     *
     * @param array<string, string> $headers
     */
    public function put(string $url, string $body = '', array $headers = [], ?ConnectionOptions $options = null): HttpResponse
    {
        return $this->send(new HttpRequest('PUT', $url, $headers, $body), $options);
    }

    /**
     * PATCH по URL.
     *
     * @param array<string, string> $headers
     */
    public function patch(string $url, string $body = '', array $headers = [], ?ConnectionOptions $options = null): HttpResponse
    {
        return $this->send(new HttpRequest('PATCH', $url, $headers, $body), $options);
    }

    /**
     * DELETE по URL.
     *
     * @param array<string, string> $headers
     */
    public function delete(string $url, array $headers = [], ?ConnectionOptions $options = null): HttpResponse
    {
        return $this->send(new HttpRequest('DELETE', $url, $headers), $options);
    }

    /**
     * POST JSON.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     */
    public function postJson(string $url, array $data, array $headers = [], ?ConnectionOptions $options = null): HttpResponse
    {
        return $this->send(HttpRequest::json('POST', $url, $data, $headers), $options);
    }

    /**
     * Form POST.
     *
     * @param array<string, scalar|null> $fields
     * @param array<string, string>      $headers
     */
    public function postForm(string $url, array $fields, array $headers = [], ?ConnectionOptions $options = null): HttpResponse
    {
        return $this->send(HttpRequest::form($url, $fields, $headers), $options);
    }

    /**
     * Обмен поверх уже открытого Connection.
     *
     * @param array<string, string> $headers
     *
     * @throws ConnectionException
     */
    public function exchange(
        ConnectionInterface $connection,
        string $method,
        string $target,
        array $headers = [],
        string $body = '',
        string $hostHeader = '',
    ): string {
        $method = strtoupper(trim($method));
        $target = $target === '' ? '/' : $target;
        $request = $this->buildRequest($method, $target, $headers, $body, $hostHeader);
        $written = $connection->write($request);

        if ($written < 1) {
            throw new ConnectionException('Не удалось отправить HTTP-запрос');
        }

        return $this->readResponse($connection);
    }

    /**
     * @param array<string, string> $headers
     */
    private function buildRequest(
        string $method,
        string $target,
        array $headers,
        string $body,
        string $hostHeader,
    ): string {
        $normalized = [];

        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = $value;
        }

        if ($hostHeader !== '' && !isset($normalized['host'])) {
            $normalized['host'] = $hostHeader;
        }

        if ($body !== '' && !isset($normalized['content-length'])) {
            $normalized['content-length'] = (string) strlen($body);
        }

        if (!isset($normalized['connection'])) {
            $normalized['connection'] = 'close';
        }

        if (!isset($normalized['user-agent'])) {
            $normalized['user-agent'] = 'cloud-castle/connect-manager';
        }

        $lines = [sprintf('%s %s HTTP/1.1', $method, $target)];

        foreach ($normalized as $name => $value) {
            $lines[] = sprintf('%s: %s', $this->headerName($name), $value);
        }

        return implode("\r\n", $lines) . "\r\n\r\n" . $body;
    }

    /**
     * @throws ConnectionException
     */
    private function readResponse(ConnectionInterface $connection): string
    {
        $buffer = '';

        while ($connection->isOpen()) {
            $chunk = $connection->read(8192);

            if ($chunk === '') {
                break;
            }

            $buffer .= $chunk;

            if ($this->isComplete($buffer)) {
                break;
            }
        }

        if ($buffer === '') {
            throw new ConnectionException('Пустой HTTP-ответ');
        }

        return $buffer;
    }

    private function isComplete(string $buffer): bool
    {
        $separator = strpos($buffer, "\r\n\r\n");

        if ($separator === false) {
            return false;
        }

        $headerBlock = substr($buffer, 0, $separator);
        $body = substr($buffer, $separator + 4);

        if (preg_match('/^transfer-encoding:\s*chunked/im', $headerBlock) === 1) {
            return str_contains($body, "0\r\n\r\n");
        }

        if (preg_match('/^content-length:\s*(\d+)/im', $headerBlock, $matches) === 1) {
            return strlen($body) >= (int) $matches[1];
        }

        return false;
    }

    private function headerName(string $name): string
    {
        $parts = explode('-', $name);

        foreach ($parts as $index => $part) {
            $parts[$index] = ucfirst($part);
        }

        return implode('-', $parts);
    }

    /**
     * @param array<string, scalar|null> $query
     */
    private function applyQuery(string $url, array $query): string
    {
        if ($query === []) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query($query);
    }

    /**
     * @return array{scheme: string, host: string, port: int, path: string, query: string}
     *
     * @throws ConnectionException
     */
    private function parseUrl(string $url): array
    {
        $parts = parse_url($url);

        if (!is_array($parts) || !isset($parts['host']) || $parts['host'] === '') {
            throw new ConnectionException('Некорректный URL: ' . $url);
        }

        $scheme = strtolower($parts['scheme'] ?? 'http');

        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new ConnectionException('Поддерживаются только http/https: ' . $url);
        }

        $path = $parts['path'] ?? '/';

        if ($path === '') {
            $path = '/';
        }

        return [
            'scheme' => $scheme,
            'host' => $parts['host'],
            'port' => $parts['port'] ?? ($scheme === 'https' ? 443 : 80),
            'path' => $path,
            'query' => isset($parts['query']) ? '?' . $parts['query'] : '',
        ];
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    private function withHostAndAuth(
        array $headers,
        string $host,
        int $port,
        ?AuthCredentials $auth,
    ): array {
        if (!isset($headers['Host']) && !isset($headers['host'])) {
            $headers['Host'] = ($port === 80 || $port === 443) ? $host : $host . ':' . $port;
        }

        if ($auth instanceof AuthCredentials && $auth->password() !== null) {
            $headers['Authorization'] = 'Basic ' . base64_encode($auth->username() . ':' . $auth->password());
        }

        return $headers;
    }

    private function resolveRedirect(string $currentUrl, string $location): string
    {
        if (str_contains($location, '://')) {
            return $location;
        }

        $parts = parse_url($currentUrl);

        if (!is_array($parts)) {
            return $location;
        }

        $scheme = $parts['scheme'] ?? 'http';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $basePath = $parts['path'] ?? '/';

        if (str_starts_with($location, '/')) {
            return $scheme . '://' . $host . $port . $location;
        }

        $dir = preg_replace('#/[^/]*$#', '/', $basePath) ?? '/';

        return $scheme . '://' . $host . $port . $dir . $location;
    }
}

<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Driver\Http;

/**
 * HTTP-запрос прикладного клиента.
 */
final class HttpRequest
{
    /**
     * @param array<string, string>       $headers
     * @param array<string, scalar|null>  $query
     */
    public function __construct(
        private readonly string $method,
        private readonly string $url,
        private readonly array $headers = [],
        private readonly string $body = '',
        private readonly array $query = [],
        private readonly int $maxRedirects = 5,
    ) {
    }

    /**
     * GET-запрос.
     *
     * @param array<string, string> $headers
     * @param array<string, scalar|null> $query
     */
    public static function get(string $url, array $headers = [], array $query = []): self
    {
        return new self('GET', $url, $headers, '', $query);
    }

    /**
     * POST с телом.
     *
     * @param array<string, string> $headers
     */
    public static function post(string $url, string $body = '', array $headers = []): self
    {
        return new self('POST', $url, $headers, $body);
    }

    /**
     * POST JSON.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     */
    public static function json(string $method, string $url, array $data, array $headers = []): self
    {
        $headers['Content-Type'] = 'application/json';
        $encoded = json_encode($data, JSON_THROW_ON_ERROR);

        return new self(strtoupper($method), $url, $headers, $encoded);
    }

    /**
     * application/x-www-form-urlencoded.
     *
     * @param array<string, scalar|null> $fields
     * @param array<string, string>      $headers
     */
    public static function form(string $url, array $fields, array $headers = []): self
    {
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';

        return new self('POST', $url, $headers, http_build_query($fields));
    }

    /**
     * HTTP-метод.
     */
    public function method(): string
    {
        return strtoupper($this->method);
    }

    /**
     * URL.
     */
    public function url(): string
    {
        return $this->url;
    }

    /**
     * Заголовки.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Тело.
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Query-параметры.
     *
     * @return array<string, scalar|null>
     */
    public function query(): array
    {
        return $this->query;
    }

    /**
     * Максимум редиректов.
     */
    public function maxRedirects(): int
    {
        return max(0, $this->maxRedirects);
    }

    /**
     * Копия с лимитом редиректов.
     */
    public function withMaxRedirects(int $maxRedirects): self
    {
        return new self(
            $this->method,
            $this->url,
            $this->headers,
            $this->body,
            $this->query,
            $maxRedirects,
        );
    }
}

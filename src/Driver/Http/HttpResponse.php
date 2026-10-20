<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Driver\Http;

use CloudCastle\ConnectManager\Exception\ConnectionException;

/**
 * Разобранный HTTP-ответ.
 */
final class HttpResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly string $reasonPhrase,
        private readonly array $headers,
        private readonly string $body,
        private readonly string $raw,
    ) {
    }

    /**
     * Разбирает сырой HTTP-ответ.
     *
     * @throws ConnectionException
     */
    public static function fromRaw(string $raw): self
    {
        $separator = strpos($raw, "\r\n\r\n");

        if ($separator === false) {
            throw new ConnectionException('Некорректный HTTP-ответ: нет разделителя заголовков');
        }

        $headerBlock = substr($raw, 0, $separator);
        $body = substr($raw, $separator + 4);
        $split = preg_split("/\r\n/", $headerBlock);
        $lines = is_array($split) ? $split : [];
        $statusLine = $lines[0] ?? '';
        array_shift($lines);

        if (preg_match('#^HTTP/\d+(?:\.\d+)?\s+(\d{3})\s*(.*)$#i', $statusLine, $matches) !== 1) {
            throw new ConnectionException('Некорректная status-line HTTP');
        }

        $headers = [];

        foreach ($lines as $line) {
            $pos = strpos($line, ':');

            if ($pos === false) {
                continue;
            }

            $name = strtolower(trim(substr($line, 0, $pos)));
            $value = trim(substr($line, $pos + 1));
            $headers[$name] = $value;
        }

        return new self((int) $matches[1], trim($matches[2]), $headers, $body, $raw);
    }

    /**
     * Код статуса.
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Reason phrase.
     */
    public function reasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    /**
     * Заголовки (lowercase keys).
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Значение заголовка.
     */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * Тело ответа.
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Сырой ответ.
     */
    public function raw(): string
    {
        return $this->raw;
    }

    /**
     * Успешный 2xx.
     */
    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Редирект 3xx.
     */
    public function isRedirect(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    /**
     * JSON-тело.
     *
     * @return array<mixed>|scalar|null
     *
     * @throws ConnectionException
     */
    public function json(): mixed
    {
        try {
            /** @var array<mixed>|scalar|null $decoded */
            $decoded = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ConnectionException('Тело ответа не является JSON: ' . $exception->getMessage());
        }

        return $decoded;
    }
}

<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Contract;

/**
 * Опции открытия и эксплуатации соединения.
 *
 * @SuppressWarnings("PHPMD.BooleanArgumentFlag")
 * @SuppressWarnings("PHPMD.LongVariable")
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 * @SuppressWarnings("PHPMD.CyclomaticComplexity")
 */
final class ConnectionOptions
{
    /**
     * Таймаут соединения в секундах.
     */
    private readonly float $connectTimeout;

    /**
     * Таймаут чтения/записи в секундах.
     */
    private readonly float $ioTimeout;

    /**
     * Режим: sync или async-aware (не блокирует фабрику, влияет на runtime).
     */
    private readonly string $mode;

    /**
     * @param array<string, mixed> $streamContext
     */
    public function __construct(
        float $connectTimeout = 5.0,
        float $ioTimeout = 30.0,
        /**
         * Проверять TLS peer name / certificate.
         */
        private readonly bool $verifyPeer = true,
        /**
         * Блокировать частные/link-local адреса (анти-SSRF).
         */
        private readonly bool $blockPrivateAddresses = true,
        /**
         * Разрешить plaintext без TLS для схем, где TLS опционален.
         */
        private readonly bool $allowInsecure = false,
        /**
         * Дополнительные флаги контекста stream.
         */
        private readonly array $streamContext = [],
        string $mode = 'sync',
        /**
         * Учётные данные SSH/SFTP / HTTP Basic.
         */
        private readonly ?AuthCredentials $auth = null,
    ) {
        $this->connectTimeout = $connectTimeout > 0.0 ? $connectTimeout : 5.0;
        $this->ioTimeout = $ioTimeout > 0.0 ? $ioTimeout : 30.0;
        $this->mode = $mode === 'async' ? 'async' : 'sync';
    }

    /**
     * Создаёт опции с безопасными значениями по умолчанию.
     */
    public static function defaults(): self
    {
        return new self();
    }

    /**
     * Возвращает таймаут connect.
     */
    public function connectTimeout(): float
    {
        return $this->connectTimeout;
    }

    /**
     * Возвращает таймаут I/O.
     */
    public function ioTimeout(): float
    {
        return $this->ioTimeout;
    }

    /**
     * Нужна ли проверка TLS peer.
     */
    public function verifyPeer(): bool
    {
        return $this->verifyPeer;
    }

    /**
     * Блокируются ли частные адреса.
     */
    public function blockPrivateAddresses(): bool
    {
        return $this->blockPrivateAddresses;
    }

    /**
     * Разрешён ли небезопасный plaintext.
     */
    public function allowInsecure(): bool
    {
        return $this->allowInsecure;
    }

    /**
     * Доп. контекст stream.
     *
     * @return array<string, mixed>
     */
    public function streamContext(): array
    {
        return $this->streamContext;
    }

    /**
     * Режим sync|async.
     */
    public function mode(): string
    {
        return $this->mode;
    }

    /**
     * Учётные данные или null.
     */
    public function auth(): ?AuthCredentials
    {
        return $this->auth;
    }

    /**
     * Копия с другим режимом.
     */
    public function withMode(string $mode): self
    {
        return $this->copy(mode: $mode);
    }

    /**
     * Копия с отключённой блокировкой частных адресов (для тестов/localhost).
     */
    public function allowPrivateAddresses(): self
    {
        return $this->copy(blockPrivateAddresses: false);
    }

    /**
     * Копия с учётными данными.
     */
    public function withAuth(AuthCredentials $auth): self
    {
        return $this->copy(auth: $auth, replaceAuth: true);
    }

    /**
     * Копия с TLS peer_name / SNI для IP-pinned connect.
     */
    public function withTlsPeerName(string $peerName): self
    {
        $context = $this->streamContext;
        $ssl = [];

        if (isset($context['ssl']) && is_array($context['ssl'])) {
            /** @var array<string, mixed> $ssl */
            $ssl = $context['ssl'];
        }

        $ssl['peer_name'] = $peerName;
        $context['ssl'] = $ssl;

        return $this->copy(streamContext: $context);
    }

    /**
     * Создаёт копию с переопределёнными полями.
     *
     * @param array<string, mixed>|null $streamContext
     */
    private function copy(
        ?float $connectTimeout = null,
        ?float $ioTimeout = null,
        ?bool $verifyPeer = null,
        ?bool $blockPrivateAddresses = null,
        ?bool $allowInsecure = null,
        ?array $streamContext = null,
        ?string $mode = null,
        ?AuthCredentials $auth = null,
        bool $replaceAuth = false,
    ): self {
        return new self(
            $this->preferFloat($connectTimeout, $this->connectTimeout),
            $this->preferFloat($ioTimeout, $this->ioTimeout),
            $this->preferBool($verifyPeer, $this->verifyPeer),
            $this->preferBool($blockPrivateAddresses, $this->blockPrivateAddresses),
            $this->preferBool($allowInsecure, $this->allowInsecure),
            $this->preferContext($streamContext, $this->streamContext),
            $this->preferString($mode, $this->mode),
            $this->preferAuth($auth, $this->auth, $replaceAuth),
        );
    }

    private function preferFloat(?float $override, float $current): float
    {
        return $override ?? $current;
    }

    private function preferBool(?bool $override, bool $current): bool
    {
        return $override ?? $current;
    }

    private function preferString(?string $override, string $current): string
    {
        return $override ?? $current;
    }

    /**
     * @param array<string, mixed>|null $override
     * @param array<string, mixed>      $current
     *
     * @return array<string, mixed>
     */
    private function preferContext(?array $override, array $current): array
    {
        return $override ?? $current;
    }

    private function preferAuth(?AuthCredentials $override, ?AuthCredentials $current, bool $replace): ?AuthCredentials
    {
        if ($replace || $override instanceof AuthCredentials) {
            return $override;
        }

        return $current;
    }
}

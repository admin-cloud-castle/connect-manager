<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Contract;

/**
 * Учётные данные для SSH/SFTP и HTTP Basic Auth.
 */
final class AuthCredentials
{
    /**
     * @param string      $username   Имя пользователя
     * @param string|null $password   Пароль (password auth / HTTP basic)
     * @param string|null $privateKey PEM/путь к приватному ключу SSH
     * @param string|null $passphrase Пароль к ключу
     */
    public function __construct(
        private readonly string $username,
        private readonly ?string $password = null,
        private readonly ?string $privateKey = null,
        private readonly ?string $passphrase = null,
    ) {
    }

    /**
     * Аутентификация по паролю.
     */
    public static function withPassword(string $username, string $password): self
    {
        return new self($username, $password);
    }

    /**
     * Аутентификация по публичному ключу.
     */
    public static function withPublicKey(string $username, string $privateKey, ?string $passphrase = null): self
    {
        return new self($username, null, $privateKey, $passphrase);
    }

    /**
     * Имя пользователя.
     */
    public function username(): string
    {
        return $this->username;
    }

    /**
     * Пароль или null.
     */
    public function password(): ?string
    {
        return $this->password;
    }

    /**
     * Приватный ключ или null.
     */
    public function privateKey(): ?string
    {
        return $this->privateKey;
    }

    /**
     * Passphrase ключа или null.
     */
    public function passphrase(): ?string
    {
        return $this->passphrase;
    }
}

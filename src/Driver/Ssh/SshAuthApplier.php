<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Driver\Ssh;

use CloudCastle\ConnectManager\Contract\AuthCredentials;
use CloudCastle\ConnectManager\Exception\ConnectionException;
use phpseclib3\Crypt\Common\PrivateKey;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

/**
 * Применяет AuthCredentials к phpseclib SSH2.
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.ShortVariable")
 */
final class SshAuthApplier
{
    /**
     * Логин по паролю или ключу.
     *
     * @throws ConnectionException
     */
    public function login(SSH2 $ssh, AuthCredentials $auth): void
    {
        $success = $this->attemptLogin($ssh, $auth);

        if ($success) {
            return;
        }

        throw new ConnectionException('SSH-аутентификация не удалась для пользователя ' . $auth->username());
    }

    /**
     * @throws ConnectionException
     */
    private function attemptLogin(SSH2 $ssh, AuthCredentials $auth): bool
    {
        if ($auth->privateKey() !== null && $auth->privateKey() !== '') {
            return $this->loginWithKey($ssh, $auth);
        }

        if ($auth->password() !== null) {
            return $ssh->login($auth->username(), $auth->password());
        }

        return false;
    }

    /**
     * @throws ConnectionException
     */
    private function loginWithKey(SSH2 $ssh, AuthCredentials $auth): bool
    {
        $material = $auth->privateKey() ?? '';
        $passphrase = $auth->passphrase() ?? '';
        $keyMaterial = $this->loadKeyMaterial($material);

        try {
            $key = PublicKeyLoader::load($keyMaterial, $passphrase);
        } catch (\Throwable $exception) {
            throw new ConnectionException('Не удалось загрузить SSH-ключ: ' . $exception->getMessage());
        }

        if (!$key instanceof PrivateKey) {
            throw new ConnectionException('SSH-ключ не является приватным ключом');
        }

        return $ssh->login($auth->username(), $key);
    }

    /**
     * @throws ConnectionException
     */
    private function loadKeyMaterial(string $material): string
    {
        if (!is_file($material)) {
            return $material;
        }

        $contents = file_get_contents($material);

        if (!is_string($contents)) {
            throw new ConnectionException('Не удалось прочитать SSH-ключ: ' . $material);
        }

        return $contents;
    }
}

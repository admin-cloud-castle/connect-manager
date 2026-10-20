<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Driver\Ssh;

use CloudCastle\ConnectManager\Contract\AuthCredentials;
use CloudCastle\ConnectManager\Exception\ConnectionException;
use OpenSSLAsymmetricKey;

/**
 * Загрузка ключа и подпись USERAUTH publickey (RSA / Ed25519).
 *
 * @SuppressWarnings("PHPMD.ErrorControlOperator")
 * @SuppressWarnings("PHPMD.CyclomaticComplexity")
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.NPathComplexity")
 */
final class SshPublicKeyAuth
{
    /**
     * @return array{
     *     algorithm: string,
     *     publicBlob: string,
     *     key: OpenSSLAsymmetricKey,
     *     ed25519Secret: string|null
     * }
     *
     * @throws ConnectionException
     */
    public function load(AuthCredentials $auth): array
    {
        $material = $this->readMaterial($auth->privateKey() ?? '');
        $passphrase = $auth->passphrase() ?? '';
        $key = openssl_pkey_get_private($material, $passphrase);

        if ($key === false) {
            $error = openssl_error_string();
            throw new ConnectionException(
                'Не удалось загрузить SSH private key: ' . ($error !== false ? $error : ''),
            );
        }

        $details = openssl_pkey_get_details($key);

        if (!is_array($details) || !isset($details['type']) || !is_int($details['type'])) {
            throw new ConnectionException('Не удалось прочитать параметры SSH-ключа');
        }

        $type = $details['type'];

        if ($type === OPENSSL_KEYTYPE_RSA) {
            return $this->rsaParts($key, $details['rsa'] ?? null);
        }

        if (defined('OPENSSL_KEYTYPE_ED25519') && $type === OPENSSL_KEYTYPE_ED25519) {
            return $this->ed25519Parts($key, $details['ed25519'] ?? null);
        }

        throw new ConnectionException('Поддерживаются RSA и Ed25519 private keys (PEM)');
    }

    /**
     * Собирает USERAUTH_REQUEST publickey (с подписью).
     *
     * @param array{
     *     algorithm: string,
     *     publicBlob: string,
     *     key: OpenSSLAsymmetricKey,
     *     ed25519Secret: string|null
     * } $parts
     *
     * @throws ConnectionException
     */
    public function buildRequest(string $sessionId, string $username, array $parts): string
    {
        $signatureBlob = $this->sign($sessionId, $username, $parts);

        return chr(50)
            . SshBinary::sshString($username)
            . SshBinary::sshString('ssh-connection')
            . SshBinary::sshString('publickey')
            . "\x01"
            . SshBinary::sshString($parts['algorithm'])
            . SshBinary::sshString($parts['publicBlob'])
            . SshBinary::sshString($signatureBlob);
    }

    /**
     * @param array{
     *     algorithm: string,
     *     publicBlob: string,
     *     key: OpenSSLAsymmetricKey,
     *     ed25519Secret: string|null
     * } $parts
     *
     * @throws ConnectionException
     */
    private function sign(string $sessionId, string $username, array $parts): string
    {
        $toSign = SshBinary::sshString($sessionId)
            . chr(50)
            . SshBinary::sshString($username)
            . SshBinary::sshString('ssh-connection')
            . SshBinary::sshString('publickey')
            . "\x01"
            . SshBinary::sshString($parts['algorithm'])
            . SshBinary::sshString($parts['publicBlob']);

        $algorithm = $parts['algorithm'];

        if ($algorithm === 'ssh-ed25519') {
            $signature = $this->signEd25519($toSign, $parts);
        } elseif ($algorithm === 'rsa-sha2-512') {
            $signature = $this->opensslSign($toSign, $parts['key'], OPENSSL_ALGO_SHA512, 'RSA-SHA512');
        } elseif ($algorithm === 'rsa-sha2-256') {
            $signature = $this->opensslSign($toSign, $parts['key'], OPENSSL_ALGO_SHA256, 'RSA-SHA256');
        } else {
            $signature = $this->opensslSign($toSign, $parts['key'], OPENSSL_ALGO_SHA1, 'RSA-SHA1');
        }

        return SshBinary::sshString($algorithm) . SshBinary::sshString($signature);
    }

    /**
     * @throws ConnectionException
     */
    private function opensslSign(string $toSign, OpenSSLAsymmetricKey $key, int $algorithm, string $label): string
    {
        $signature = '';
        $ok = openssl_sign($toSign, $signature, $key, $algorithm);

        if (!$ok) {
            throw new ConnectionException($label . ' подпись не удалась');
        }

        return $this->requireSignatureBytes($signature, $label);
    }

    /**
     * @param array{
     *     algorithm: string,
     *     publicBlob: string,
     *     key: OpenSSLAsymmetricKey,
     *     ed25519Secret: string|null
     * } $parts
     *
     * @throws ConnectionException
     */
    private function signEd25519(string $toSign, array $parts): string
    {
        $secret = $parts['ed25519Secret'];

        if (is_string($secret) && $secret !== '' && function_exists('sodium_crypto_sign_detached')) {
            return sodium_crypto_sign_detached($toSign, $secret);
        }

        $signature = '';
        $ok = openssl_sign($toSign, $signature, $parts['key'], 0);

        if (!$ok) {
            $error = openssl_error_string();
            throw new ConnectionException(
                'Ed25519 подпись не удалась: ' . ($error !== false ? $error : ''),
            );
        }

        return $this->requireSignatureBytes($signature, 'Ed25519');
    }

    /**
     * @throws ConnectionException
     */
    private function requireSignatureBytes(mixed $signature, string $label): string
    {
        if (!is_string($signature) || $signature === '') {
            throw new ConnectionException($label . ' подпись не удалась');
        }

        return $signature;
    }

    /**
     * @throws ConnectionException
     */
    private function readMaterial(string $privateKey): string
    {
        if ($privateKey === '') {
            throw new ConnectionException('Пустой private key');
        }

        if (is_file($privateKey)) {
            $contents = file_get_contents($privateKey);

            if (!is_string($contents) || $contents === '') {
                throw new ConnectionException('Не удалось прочитать файл ключа: ' . $privateKey);
            }

            return $contents;
        }

        return $privateKey;
    }

    /**
     * @return array{
     *     algorithm: string,
     *     publicBlob: string,
     *     key: OpenSSLAsymmetricKey,
     *     ed25519Secret: string|null
     * }
     *
     * @throws ConnectionException
     */
    private function rsaParts(OpenSSLAsymmetricKey $key, mixed $rsa): array
    {
        if (!is_array($rsa) || !isset($rsa['n'], $rsa['e']) || !is_string($rsa['n']) || !is_string($rsa['e'])) {
            throw new ConnectionException('RSA-ключ без модуля/экспоненты');
        }

        $blob = SshBinary::sshString('ssh-rsa')
            . SshBinary::mpint($rsa['e'])
            . SshBinary::mpint($rsa['n']);

        return [
            'algorithm' => 'rsa-sha2-256',
            'publicBlob' => $blob,
            'key' => $key,
            'ed25519Secret' => null,
        ];
    }

    /**
     * @return array{
     *     algorithm: string,
     *     publicBlob: string,
     *     key: OpenSSLAsymmetricKey,
     *     ed25519Secret: string|null
     * }
     *
     * @throws ConnectionException
     */
    private function ed25519Parts(OpenSSLAsymmetricKey $key, mixed $ed): array
    {
        if (!is_array($ed) || !isset($ed['pub_key'], $ed['priv_key'])) {
            throw new ConnectionException('Ed25519: нет pub_key/priv_key в openssl details');
        }

        $pub = $ed['pub_key'];
        $priv = $ed['priv_key'];

        if (!is_string($pub) || strlen($pub) !== 32 || !is_string($priv) || strlen($priv) !== 32) {
            throw new ConnectionException('Ed25519: некорректная длина ключей');
        }

        $blob = SshBinary::sshString('ssh-ed25519') . SshBinary::sshString($pub);

        return [
            'algorithm' => 'ssh-ed25519',
            'publicBlob' => $blob,
            'key' => $key,
            'ed25519Secret' => $priv . $pub,
        ];
    }
}

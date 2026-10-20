<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\Contract\AuthCredentials;
use CloudCastle\ConnectManager\Driver\Ssh\SshPublicKeyAuth;
use CloudCastle\ConnectManager\Exception\ConnectionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Дополнительные ветки SshPublicKeyAuth (ошибки и альтернативные алгоритмы).
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
#[CoversClass(SshPublicKeyAuth::class)]
#[UsesClass(AuthCredentials::class)]
#[UsesClass(\CloudCastle\ConnectManager\Driver\Ssh\SshBinary::class)]
final class SshPublicKeyAuthCoverageTest extends TestCase
{
    /**
     * Пустой файл ключа.
     */
    public function testEmptyKeyFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ccm-empty');
        self::assertNotFalse($path);
        file_put_contents($path, '');
        $this->expectException(ConnectionException::class);

        try {
            (new SshPublicKeyAuth())->load(AuthCredentials::withPublicKey('u', $path));
        } finally {
            unlink($path);
        }
    }

    /**
     * rsa-sha2-256 default algorithm в подписи (ветка match default / SHA1 fallback).
     */
    public function testRsaSha256SignBranch(): void
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        self::assertNotFalse($key);
        $pemBuffer = '';
        self::assertTrue(openssl_pkey_export($key, $pemBuffer));
        $pem = $this->requireExportedPem($pemBuffer);
        $helper = new SshPublicKeyAuth();
        $parts = $helper->load(AuthCredentials::withPublicKey('u', $pem));
        $request = $helper->buildRequest(str_repeat("\x04", 32), 'u', $parts);
        self::assertStringContainsString('rsa-sha2-256', $request);

        $parts['algorithm'] = 'ssh-rsa';
        $legacy = $helper->buildRequest(str_repeat("\x05", 32), 'u', $parts);
        self::assertStringContainsString('ssh-rsa', $legacy);

        $parts['algorithm'] = 'rsa-sha1-legacy';
        $sha1 = $helper->buildRequest(str_repeat("\x06", 32), 'u', $parts);
        self::assertStringContainsString('rsa-sha1-legacy', $sha1);
    }

    /**
     * rsaParts без модуля (через reflection).
     */
    public function testRsaPartsMissingModulus(): void
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        self::assertNotFalse($key);
        $method = new ReflectionMethod(SshPublicKeyAuth::class, 'rsaParts');
        $this->expectException(ConnectionException::class);
        $method->invoke(new SshPublicKeyAuth(), $key, null);
    }

    /**
     * ed25519Parts с некорректной длиной (через reflection, без OPENSSL_KEYTYPE_ED25519).
     */
    public function testEd25519PartsBadLength(): void
    {
        $key = $this->requireRsaKey();
        $method = new ReflectionMethod(SshPublicKeyAuth::class, 'ed25519Parts');
        $this->expectException(ConnectionException::class);
        $method->invoke(new SshPublicKeyAuth(), $key, ['pub_key' => 'short', 'priv_key' => 'short']);
    }

    /**
     * ed25519Parts без массива details.
     */
    public function testEd25519PartsMissingDetails(): void
    {
        $key = $this->requireRsaKey();
        $method = new ReflectionMethod(SshPublicKeyAuth::class, 'ed25519Parts');
        $this->expectException(ConnectionException::class);
        $method->invoke(new SshPublicKeyAuth(), $key, null);
    }

    /**
     * ed25519Parts: успешная сборка blob и secret (32+32 байта).
     */
    public function testEd25519PartsSuccess(): void
    {
        $key = $this->requireRsaKey();
        $pub = str_repeat("\x11", 32);
        $priv = str_repeat("\x22", 32);
        $method = new ReflectionMethod(SshPublicKeyAuth::class, 'ed25519Parts');
        /** @var array{algorithm: string, publicBlob: string, ed25519Secret: string} $parts */
        $parts = $method->invoke(new SshPublicKeyAuth(), $key, ['pub_key' => $pub, 'priv_key' => $priv]);
        self::assertSame('ssh-ed25519', $parts['algorithm']);
        self::assertSame($priv . $pub, $parts['ed25519Secret']);
        self::assertStringContainsString('ssh-ed25519', $parts['publicBlob']);
    }

    /**
     * Ветка match ssh-ed25519 в sign()/buildRequest через sodium (без OPENSSL_KEYTYPE_ED25519).
     */
    public function testSignEd25519ViaSodium(): void
    {
        if (!function_exists('sodium_crypto_sign_keypair')) {
            self::markTestSkipped('sodium недоступен');
        }

        $keypair = sodium_crypto_sign_keypair();
        $secret = sodium_crypto_sign_secretkey($keypair);
        $public = sodium_crypto_sign_publickey($keypair);
        $key = $this->requireRsaKey();
        $parts = [
            'algorithm' => 'ssh-ed25519',
            'publicBlob' => "\x00\x00\x00\x0bssh-ed25519" . "\x00\x00\x00\x20" . $public,
            'key' => $key,
            'ed25519Secret' => $secret,
        ];
        $request = (new SshPublicKeyAuth())->buildRequest(str_repeat("\x07", 32), 'u', $parts);
        self::assertSame(50, ord($request[0]));
        self::assertStringContainsString('ssh-ed25519', $request);
    }

    /**
     * requireSignatureBytes при пустой подписи.
     */
    public function testRequireSignatureBytesEmpty(): void
    {
        $method = new ReflectionMethod(SshPublicKeyAuth::class, 'requireSignatureBytes');
        $this->expectException(ConnectionException::class);
        $method->invoke(new SshPublicKeyAuth(), '', 'Test');
    }

    private function requireRsaKey(): \OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        self::assertNotFalse($key);

        return $key;
    }

    /**
     * @param mixed $exported Результат openssl_pkey_export by-ref
     */
    private function requireExportedPem(mixed $exported): string
    {
        self::assertIsString($exported);
        self::assertNotSame('', $exported);

        return $exported;
    }
}

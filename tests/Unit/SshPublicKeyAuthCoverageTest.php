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
        openssl_pkey_export($key, $pem);
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
     * ed25519Parts с некорректной длиной (через reflection).
     */
    public function testEd25519PartsBadLength(): void
    {
        if (!defined('OPENSSL_KEYTYPE_ED25519')) {
            self::markTestSkipped('OPENSSL_KEYTYPE_ED25519 недоступен');
        }

        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_ED25519]);
        self::assertNotFalse($key);
        $method = new ReflectionMethod(SshPublicKeyAuth::class, 'ed25519Parts');
        $this->expectException(ConnectionException::class);
        $method->invoke(new SshPublicKeyAuth(), $key, ['pub_key' => 'short', 'priv_key' => 'short']);
    }

    /**
     * ed25519Parts без массива details.
     */
    public function testEd25519PartsMissingDetails(): void
    {
        if (!defined('OPENSSL_KEYTYPE_ED25519')) {
            self::markTestSkipped('OPENSSL_KEYTYPE_ED25519 недоступен');
        }

        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_ED25519]);
        self::assertNotFalse($key);
        $method = new ReflectionMethod(SshPublicKeyAuth::class, 'ed25519Parts');
        $this->expectException(ConnectionException::class);
        $method->invoke(new SshPublicKeyAuth(), $key, null);
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
}

<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Unit;

use CloudCastle\ConnectManager\Contract\AuthCredentials;
use CloudCastle\ConnectManager\Driver\Ssh\SshBinary;
use CloudCastle\ConnectManager\Driver\Ssh\SshPublicKeyAuth;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Publickey auth: RSA и Ed25519.
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.ErrorControlOperator")
 */
#[CoversClass(SshPublicKeyAuth::class)]
#[CoversClass(AuthCredentials::class)]
#[UsesClass(\CloudCastle\ConnectManager\Driver\Ssh\SshBinary::class)]
final class SshPublicKeyAuthTest extends TestCase
{
    /**
     * RSA PEM → rsa-sha2-256 blob и подписанный USERAUTH_REQUEST.
     */
    public function testRsaPublicKeyRequest(): void
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        self::assertNotFalse($key);
        $pem = $this->exportPem($key);

        $auth = AuthCredentials::withPublicKey('deploy', $pem);
        self::assertSame($pem, $auth->privateKey());
        self::assertNull($auth->passphrase());

        $helper = new SshPublicKeyAuth();
        $parts = $helper->load($auth);
        self::assertSame('rsa-sha2-256', $parts['algorithm']);
        $prefix = SshBinary::sshString('ssh-rsa');
        self::assertTrue(str_starts_with($parts['publicBlob'], $prefix));

        $request = $helper->buildRequest(str_repeat("\x01", 32), 'deploy', $parts);
        self::assertSame(50, ord($request[0]));
        self::assertStringContainsString('publickey', $request);
        self::assertStringContainsString('rsa-sha2-256', $request);
    }

    /**
     * Ed25519 → ssh-ed25519 и подпись.
     */
    public function testEd25519PublicKeyRequest(): void
    {
        if (!defined('OPENSSL_KEYTYPE_ED25519')) {
            self::markTestSkipped('OPENSSL_KEYTYPE_ED25519 недоступен');
        }

        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_ED25519]);
        self::assertNotFalse($key);
        $pem = $this->exportPem($key);

        $helper = new SshPublicKeyAuth();
        $parts = $helper->load(AuthCredentials::withPublicKey('alice', $pem));
        self::assertSame('ssh-ed25519', $parts['algorithm']);
        self::assertSame(64, strlen($parts['ed25519Secret'] ?? ''));

        $request = $helper->buildRequest(str_repeat("\x02", 32), 'alice', $parts);
        self::assertSame(50, ord($request[0]));
        self::assertStringContainsString('ssh-ed25519', $request);
    }

    private function exportPem(\OpenSSLAsymmetricKey $key): string
    {
        $tempDirectory = sys_get_temp_dir();
        $path = tempnam($tempDirectory, 'ccmkey');
        self::assertNotFalse($path);
        $resolvedPath = realpath($path);
        self::assertNotFalse($resolvedPath);
        $resolvedTemp = realpath($tempDirectory);
        self::assertNotFalse($resolvedTemp);
        self::assertSame(0, strpos($resolvedPath, $resolvedTemp));
        self::assertTrue(openssl_pkey_export_to_file($key, $resolvedPath));
        $pem = file_get_contents($resolvedPath);
        unlink($resolvedPath);
        self::assertNotFalse($pem);
        self::assertNotSame('', $pem);

        return $pem;
    }
}

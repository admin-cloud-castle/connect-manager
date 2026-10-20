<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Driver\Ssh;

use CloudCastle\ConnectManager\Contract\AuthCredentials;
use CloudCastle\ConnectManager\Exception\ConnectionException;

/**
 * Нативная SSH-2 сессия на PHP openssl (без Composer runtime-зависимостей).
 *
 * @SuppressWarnings("PHPMD.ErrorControlOperator")
 * @SuppressWarnings("PHPMD.CyclomaticComplexity")
 * @SuppressWarnings("PHPMD.NPathComplexity")
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 * @SuppressWarnings("PHPMD.ExcessiveClassLength")
 * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.ShortVariable")
 */
final class NativeSshSession implements SshSessionInterface, SftpChannelInterface
{
    private const MSG_DISCONNECT = 1;

    private const MSG_SERVICE_REQUEST = 5;

    private const MSG_SERVICE_ACCEPT = 6;

    private const MSG_KEXINIT = 20;

    private const MSG_NEWKEYS = 21;

    private const MSG_KEXDH_INIT = 30;

    private const MSG_KEXDH_REPLY = 31;

    private const MSG_USERAUTH_REQUEST = 50;

    private const MSG_USERAUTH_FAILURE = 51;

    private const MSG_USERAUTH_SUCCESS = 52;

    private const MSG_CHANNEL_OPEN = 90;

    private const MSG_CHANNEL_OPEN_CONFIRMATION = 91;

    private const MSG_CHANNEL_DATA = 94;

    private const MSG_CHANNEL_EOF = 96;

    private const MSG_CHANNEL_CLOSE = 97;

    private const MSG_CHANNEL_REQUEST = 98;

    private const MSG_CHANNEL_SUCCESS = 99;

    private const GROUP14_P = 'FFFFFFFFFFFFFFFFC90FDAA22168C234C4C6628B80DC1CD129024E088A67CC74020BBEA63B139B22514A08798E3404DDEF9519B3CD3A431B302B0A6DF25F14374FE1356D6D51C245E485B576625E7EC6F44C42E9A637ED6B0BFF5CB6F406B7EDEE386BFB5A899FA5AE9F24117C4B1FE649286651ECE45B3DC2007CB8A163BF0598DA48361C55D39A69163FA8FD24CF5F83655D23DCA3AD961C62F356208552BB9ED529077096966D670C354E4ABC9804F1746C08CA18217C32905E462E36CE3BE39E772C180E86039B2783A2EC07A28FB5C55DF06F4C52C9DE2BCBF6955817183995497CEA956AE515D2261898FA051015728E5A8AACAA68FFFFFFFFFFFFFFFF';

    private const CLIENT_ID = 'SSH-2.0-CloudCastle_ConnectManager_1.0';

    private SshWire $wire;

    private string $serverId = '';

    private string $sessionId = '';

    private bool $connected = false;

    private bool $sftpReady = false;

    private int $localChannel = 0;

    private int $remoteChannel = 0;

    private string $channelBuffer = '';

    private bool $channelEof = false;

    /**
     * @throws ConnectionException
     */
    public function __construct(mixed $stream, AuthCredentials $auth, bool $preferSftp = false)
    {
        if (!is_resource($stream)) {
            throw new ConnectionException('Ожидался stream resource для SSH');
        }

        if (!function_exists('gmp_init')) {
            throw new ConnectionException('Для нативного SSH требуется расширение gmp');
        }

        $this->wire = new SshWire($stream);
        $this->exchangeIdentification($stream);
        $this->performKeyExchange();
        $this->requestService('ssh-userauth');
        $this->authenticate($auth);
        $this->connected = true;

        if ($preferSftp) {
            $this->prepareSftp();
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function disconnect(): void
    {
        if (!$this->connected) {
            return;
        }

        try {
            $this->wire->send(
                chr(self::MSG_DISCONNECT)
                . SshBinary::uint32(11)
                . SshBinary::sshString('bye')
                . SshBinary::sshString(''),
            );
        } catch (ConnectionException) {
        }

        $this->wire->close();
        $this->connected = false;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function exec(string $command): string
    {
        $this->assertConnected();
        $this->openSessionChannel();
        $this->wire->send(
            chr(self::MSG_CHANNEL_REQUEST)
            . SshBinary::uint32($this->remoteChannel)
            . SshBinary::sshString('exec')
            . "\x01"
            . SshBinary::sshString($command),
        );
        $this->expectType(self::MSG_CHANNEL_SUCCESS);

        return $this->drainChannel();
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function write(string $payload): void
    {
        $this->assertConnected();

        if ($this->remoteChannel === 0) {
            $this->openSessionChannel();
        }

        $this->wire->send(
            chr(self::MSG_CHANNEL_DATA)
            . SshBinary::uint32($this->remoteChannel)
            . SshBinary::sshString($payload),
        );
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function read(int $maxLength = 8192): string
    {
        $this->assertConnected();
        $this->pump(false);

        if ($this->channelBuffer === '') {
            return '';
        }

        $length = max(1, $maxLength);
        $chunk = substr($this->channelBuffer, 0, $length);
        $this->channelBuffer = substr($this->channelBuffer, strlen($chunk));

        return $chunk;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function openSftp(): SftpClient
    {
        $this->assertConnected();
        $this->prepareSftp();

        return new SftpClient($this);
    }

    /**
     * {@inheritdoc}
     *
     * @throws ConnectionException
     */
    #[\Override]
    public function sendSftp(string $sftpPayload): void
    {
        $this->write(SshBinary::uint32(strlen($sftpPayload)) . $sftpPayload);
    }

    /**
     * {@inheritdoc}
     *
     * @throws ConnectionException
     */
    #[\Override]
    public function receiveSftp(): string
    {
        while (strlen($this->channelBuffer) < 4) {
            $this->pump(true);
        }

        $offset = 0;
        $length = SshBinary::readUint32($this->channelBuffer, $offset);

        while (strlen($this->channelBuffer) < 4 + $length) {
            $this->pump(true);
        }

        $packet = substr($this->channelBuffer, 4, $length);
        $this->channelBuffer = substr($this->channelBuffer, 4 + $length);

        return $packet;
    }

    /**
     * @throws ConnectionException
     */
    private function exchangeIdentification(mixed $stream): void
    {
        if (!is_resource($stream)) {
            throw new ConnectionException('Ожидался stream resource для SSH');
        }

        if (@fwrite($stream, self::CLIENT_ID . "\r\n") === false) {
            throw new ConnectionException('SSH identification write не удался');
        }

        $line = '';

        while (!str_ends_with($line, "\n")) {
            $chunk = @fgets($stream, 512);

            if ($chunk === false) {
                throw new ConnectionException('SSH identification read не удался');
            }

            $line .= $chunk;
        }

        $this->serverId = trim($line);

        if (!str_starts_with($this->serverId, 'SSH-2.0-') && !str_starts_with($this->serverId, 'SSH-1.99-')) {
            throw new ConnectionException('Сервер не говорит SSH-2.0: ' . $this->serverId);
        }
    }

    /**
     * @throws ConnectionException
     */
    private function performKeyExchange(): void
    {
        $cookie = random_bytes(16);
        $clientKex = chr(self::MSG_KEXINIT) . $cookie
            . SshBinary::nameList(['diffie-hellman-group14-sha256'])
            . SshBinary::nameList(['rsa-sha2-256', 'rsa-sha2-512', 'ssh-rsa', 'ssh-ed25519'])
            . SshBinary::nameList(['aes128-ctr'])
            . SshBinary::nameList(['aes128-ctr'])
            . SshBinary::nameList(['hmac-sha2-256'])
            . SshBinary::nameList(['hmac-sha2-256'])
            . SshBinary::nameList(['none'])
            . SshBinary::nameList(['none'])
            . SshBinary::nameList([])
            . SshBinary::nameList([])
            . "\x00"
            . SshBinary::uint32(0);
        $this->wire->send($clientKex);

        $serverKex = $this->expectType(self::MSG_KEXINIT);
        $p = gmp_init(self::GROUP14_P, 16);
        $x = gmp_random_bits(256);
        $e = gmp_powm(gmp_init(2), $x, $p);
        $eBytes = $this->gmpToBytes($e);
        $this->wire->send(chr(self::MSG_KEXDH_INIT) . SshBinary::mpint($eBytes));

        $reply = $this->expectType(self::MSG_KEXDH_REPLY);
        $offset = 1;
        $hostKey = SshBinary::readString($reply, $offset);
        $fBytes = SshBinary::readMpint($reply, $offset);
        SshBinary::readString($reply, $offset);
        if ($fBytes === '') {
            throw new ConnectionException('Некорректный DH параметр f');
        }

        $f = gmp_import($fBytes);

        if (gmp_cmp($f, 0) === 0) {
            throw new ConnectionException('Некорректный DH параметр f');
        }

        $k = gmp_powm($f, $x, $p);
        $kMpint = SshBinary::mpint($this->gmpToBytes($k));
        $hash = hash(
            'sha256',
            SshBinary::sshString(self::CLIENT_ID)
            . SshBinary::sshString($this->serverId)
            . SshBinary::sshString($clientKex)
            . SshBinary::sshString($serverKex)
            . SshBinary::sshString($hostKey)
            . SshBinary::mpint($eBytes)
            . SshBinary::mpint($fBytes)
            . $kMpint,
            true,
        );
        $this->sessionId = $hash;

        $this->wire->send(chr(self::MSG_NEWKEYS));
        $this->expectType(self::MSG_NEWKEYS);
        $this->wire->enableEncryption(
            $this->deriveKey($kMpint, $hash, 'C', 16),
            $this->deriveKey($kMpint, $hash, 'D', 16),
            $this->deriveKey($kMpint, $hash, 'A', 16),
            $this->deriveKey($kMpint, $hash, 'B', 16),
            $this->deriveKey($kMpint, $hash, 'E', 32),
            $this->deriveKey($kMpint, $hash, 'F', 32),
        );
    }

    /**
     * @throws ConnectionException
     */
    private function requestService(string $service): void
    {
        $this->wire->send(chr(self::MSG_SERVICE_REQUEST) . SshBinary::sshString($service));
        $payload = $this->expectType(self::MSG_SERVICE_ACCEPT);
        $offset = 1;

        if (SshBinary::readString($payload, $offset) !== $service) {
            throw new ConnectionException('SSH service accept mismatch');
        }
    }

    /**
     * @throws ConnectionException
     */
    private function authenticate(AuthCredentials $auth): void
    {
        if ($auth->privateKey() !== null && $auth->privateKey() !== '') {
            $this->authenticatePublicKey($auth);

            return;
        }

        if ($auth->password() !== null) {
            $this->authenticatePassword($auth);

            return;
        }

        throw new ConnectionException(
            'Для SSH нужны AuthCredentials::withPassword или AuthCredentials::withPublicKey',
        );
    }

    /**
     * @throws ConnectionException
     */
    private function authenticatePassword(AuthCredentials $auth): void
    {
        $password = $auth->password();

        if ($password === null) {
            throw new ConnectionException('Пустой пароль SSH');
        }

        $this->wire->send(
            chr(self::MSG_USERAUTH_REQUEST)
            . SshBinary::sshString($auth->username())
            . SshBinary::sshString('ssh-connection')
            . SshBinary::sshString('password')
            . "\x00"
            . SshBinary::sshString($password),
        );
        $this->assertAuthSuccess();
    }

    /**
     * @throws ConnectionException
     */
    private function authenticatePublicKey(AuthCredentials $auth): void
    {
        $helper = new SshPublicKeyAuth();
        $parts = $helper->load($auth);
        $this->wire->send($helper->buildRequest($this->sessionId, $auth->username(), $parts));
        $this->assertAuthSuccess();
    }

    /**
     * @throws ConnectionException
     */
    private function assertAuthSuccess(): void
    {
        $response = $this->wire->receive();

        if ($response === '') {
            throw new ConnectionException('Пустой ответ USERAUTH');
        }

        $type = ord($response[0]);

        if ($type === self::MSG_USERAUTH_SUCCESS) {
            return;
        }

        if ($type === self::MSG_USERAUTH_FAILURE) {
            throw new ConnectionException('SSH-аутентификация отклонена');
        }

        throw new ConnectionException('Неожиданный USERAUTH type=' . $type);
    }

    /**
     * @throws ConnectionException
     */
    private function prepareSftp(): void
    {
        if ($this->sftpReady) {
            return;
        }

        $this->openSessionChannel();
        $this->wire->send(
            chr(self::MSG_CHANNEL_REQUEST)
            . SshBinary::uint32($this->remoteChannel)
            . SshBinary::sshString('subsystem')
            . "\x01"
            . SshBinary::sshString('sftp'),
        );
        $this->expectType(self::MSG_CHANNEL_SUCCESS);
        $this->sendSftp(chr(1) . SshBinary::uint32(3));
        $init = $this->receiveSftp();

        if ($init === '' || ord($init[0]) !== 2) {
            throw new ConnectionException('SFTP VERSION не получен');
        }

        $this->sftpReady = true;
    }

    /**
     * @throws ConnectionException
     */
    private function openSessionChannel(): void
    {
        if ($this->remoteChannel !== 0) {
            return;
        }

        $this->localChannel = 0;
        $this->channelBuffer = '';
        $this->channelEof = false;
        $this->wire->send(
            chr(self::MSG_CHANNEL_OPEN)
            . SshBinary::sshString('session')
            . SshBinary::uint32($this->localChannel)
            . SshBinary::uint32(2_097_152)
            . SshBinary::uint32(32_768),
        );
        $payload = $this->expectType(self::MSG_CHANNEL_OPEN_CONFIRMATION);
        $offset = 1;
        SshBinary::readUint32($payload, $offset);
        $this->remoteChannel = SshBinary::readUint32($payload, $offset);
    }

    /**
     * @throws ConnectionException
     */
    private function drainChannel(): string
    {
        $this->channelEof = false;

        while (!$this->channelEof) {
            $this->pump(true);
        }

        $output = $this->channelBuffer;
        $this->channelBuffer = '';
        $this->remoteChannel = 0;

        return $output;
    }

    /**
     * @throws ConnectionException
     */
    private function pump(bool $blocking): void
    {
        if (!$blocking && $this->channelBuffer !== '') {
            return;
        }

        $payload = $this->wire->receive();

        if ($payload === '') {
            return;
        }

        $type = ord($payload[0]);
        $offset = 1;

        if ($type === self::MSG_CHANNEL_DATA) {
            SshBinary::readUint32($payload, $offset);
            $this->channelBuffer .= SshBinary::readString($payload, $offset);

            return;
        }

        if ($type === self::MSG_CHANNEL_EOF || $type === self::MSG_CHANNEL_CLOSE) {
            $this->channelEof = true;

            return;
        }
    }

    /**
     * @throws ConnectionException
     */
    private function expectType(int $type): string
    {
        $payload = $this->wire->receive();

        if ($payload === '' || ord($payload[0]) !== $type) {
            $actual = $payload === '' ? 'empty' : (string) ord($payload[0]);

            throw new ConnectionException(sprintf('Ожидался SSH type %d, получен %s', $type, $actual));
        }

        return $payload;
    }

    private function deriveKey(string $kMpint, string $h, string $letter, int $length): string
    {
        $key = hash('sha256', $kMpint . $h . $letter . $this->sessionId, true);

        while (strlen($key) < $length) {
            $key .= hash('sha256', $kMpint . $h . $key, true);
        }

        return substr($key, 0, $length);
    }

    private function gmpToBytes(\GMP $value): string
    {
        $hex = gmp_strval($value, 16);

        if ((strlen($hex) % 2) === 1) {
            $hex = '0' . $hex;
        }

        $bin = hex2bin($hex);

        return is_string($bin) ? $bin : '';
    }

    /**
     * @throws ConnectionException
     */
    private function assertConnected(): void
    {
        if (!$this->connected) {
            throw new ConnectionException('SSH-соединение закрыто');
        }
    }
}

<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Driver\Ssh;

use CloudCastle\ConnectManager\Exception\ConnectionException;

/**
 * Низкоуровневый обмен SSH-пакетами: plain и AES-128-CTR + HMAC-SHA256.
 *
 * @SuppressWarnings("PHPMD.ErrorControlOperator")
 * @SuppressWarnings("PHPMD.CyclomaticComplexity")
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
 * @SuppressWarnings("PHPMD.NPathComplexity")
 * @SuppressWarnings("PHPMD.ShortVariable")
 */
final class SshWire
{
    /**
     * Порядковый номер исходящих пакетов (для MAC).
     */
    private int $sendSeq = 0;

    /**
     * Порядковый номер входящих пакетов (для MAC).
     */
    private int $recvSeq = 0;

    /**
     * true после успешного NEWKEYS и enableEncryption().
     */
    private bool $encrypted = false;

    /**
     * Ключ шифрования client→server (16 байт для aes-128-ctr).
     */
    private string $clientToServerKey = '';

    /**
     * Ключ шифрования server→client.
     */
    private string $serverToClientKey = '';

    /**
     * IV/counter client→server (16 байт).
     */
    private string $clientToServerIv = '';

    /**
     * IV/counter server→client (16 байт).
     */
    private string $serverToClientIv = '';

    /**
     * Ключ HMAC client→server (32 байта для hmac-sha2-256).
     */
    private string $clientToServerMacKey = '';

    /**
     * Ключ HMAC server→client.
     */
    private string $serverToClientMacKey = '';

    /**
     * TCP stream или null после close().
     *
     * @var resource|null
     */
    private $stream;

    /**
     * Привязывает wire к уже открытому TCP stream.
     *
     * @param resource $stream Поток после TCP connect (binary-safe)
     */
    public function __construct($stream)
    {
        $this->stream = $stream;
    }

    /**
     * Включает шифрование и MAC после обмена NEWKEYS.
     *
     * @param string $clientToServerKey    Ключ AES client→server (16 байт)
     * @param string $serverToClientKey    Ключ AES server→client (16 байт)
     * @param string $clientToServerIv     IV/counter client→server (16 байт)
     * @param string $serverToClientIv     IV/counter server→client (16 байт)
     * @param string $clientToServerMacKey Ключ HMAC client→server (32 байта)
     * @param string $serverToClientMacKey Ключ HMAC server→client (32 байта)
     */
    public function enableEncryption(
        string $clientToServerKey,
        string $serverToClientKey,
        string $clientToServerIv,
        string $serverToClientIv,
        string $clientToServerMacKey,
        string $serverToClientMacKey,
    ): void {
        $this->clientToServerKey = $clientToServerKey;
        $this->serverToClientKey = $serverToClientKey;
        $this->clientToServerIv = $clientToServerIv;
        $this->serverToClientIv = $serverToClientIv;
        $this->clientToServerMacKey = $clientToServerMacKey;
        $this->serverToClientMacKey = $serverToClientMacKey;
        $this->encrypted = true;
    }

    /**
     * Отправляет SSH payload (без packet_length/padding — их добавляет метод).
     *
     * @param string $payload Содержимое пакета начиная с message type byte
     *
     * @throws ConnectionException При ошибке encrypt/write или закрытом stream
     */
    public function send(string $payload): void
    {
        $block = $this->encrypted ? 16 : 8;
        $minPadding = 4;
        $payloadLength = strlen($payload);
        $paddingLength = $block - (($payloadLength + 5) % $block);

        if ($paddingLength < $minPadding) {
            $paddingLength += $block;
        }

        $packetLength = $payloadLength + $paddingLength + 1;
        $padding = random_bytes($paddingLength);
        $packet = SshBinary::uint32($packetLength) . chr($paddingLength) . $payload . $padding;

        if ($this->encrypted) {
            $mac = hash_hmac('sha256', SshBinary::uint32($this->sendSeq) . $packet, $this->clientToServerMacKey, true);
            $encrypted = openssl_encrypt(
                $packet,
                'aes-128-ctr',
                $this->clientToServerKey,
                OPENSSL_RAW_DATA,
                $this->clientToServerIv,
            );

            if ($encrypted === false) {
                throw new ConnectionException('SSH encrypt не удался');
            }

            $this->clientToServerIv = $this->advanceCtr($this->clientToServerIv, (int) (strlen($packet) / 16));
            $packet = $encrypted . $mac;
        }

        $this->writeAll($packet);
        ++$this->sendSeq;
    }

    /**
     * Читает следующий SSH-пакет и возвращает payload без padding.
     *
     * @return string Payload начиная с message type; '' не ожидается при успехе
     *
     * @throws ConnectionException При ошибке decrypt/MAC/read или битом packet_length
     */
    public function receive(): string
    {
        if (!$this->encrypted) {
            return $this->receivePlain();
        }

        return $this->receiveEncrypted();
    }

    /**
     * Читает незашифрованный SSH-пакет.
     *
     * @throws ConnectionException При ошибке чтения
     */
    private function receivePlain(): string
    {
        $header = $this->readExact(5);
        $offset = 0;
        $packetLength = SshBinary::readUint32($header, $offset);
        $paddingLength = ord($header[4]);
        $rest = $this->readExact($packetLength - 1);
        ++$this->recvSeq;

        return substr($rest, 0, $packetLength - $paddingLength - 1);
    }

    /**
     * Читает AES-128-CTR + HMAC-SHA256 пакет.
     *
     * @throws ConnectionException При decrypt/MAC/packet_length ошибке
     */
    private function receiveEncrypted(): string
    {
        $plainHeader = $this->decryptIncomingBlock($this->readExact(16));
        $offset = 0;
        $packetLength = SshBinary::readUint32($plainHeader, $offset);
        $paddingLength = ord($plainHeader[4]);
        $remaining = $packetLength + 4 - 16;

        if ($remaining < 0) {
            throw new ConnectionException('SSH: некорректный packet_length');
        }

        $restPlain = $this->decryptIncomingBody($remaining);
        $packet = $plainHeader . $restPlain;
        $mac = $this->readExact(32);
        $expected = hash_hmac(
            'sha256',
            SshBinary::uint32($this->recvSeq) . $packet,
            $this->serverToClientMacKey,
            true,
        );

        if (!hash_equals($expected, $mac)) {
            throw new ConnectionException('SSH MAC mismatch');
        }

        ++$this->recvSeq;

        return substr($packet, 5, $packetLength - $paddingLength - 1);
    }

    /**
     * Расшифровывает один 16-байтный блок и сдвигает IV.
     *
     * @throws ConnectionException При сбое openssl_decrypt
     */
    private function decryptIncomingBlock(string $cipherBlock): string
    {
        $plain = openssl_decrypt(
            $cipherBlock,
            'aes-128-ctr',
            $this->serverToClientKey,
            OPENSSL_RAW_DATA,
            $this->serverToClientIv,
        );

        if ($plain === false) {
            throw new ConnectionException('SSH decrypt header не удался');
        }

        $this->serverToClientIv = $this->advanceCtr($this->serverToClientIv, 1);

        return $plain;
    }

    /**
     * Расшифровывает остаток тела пакета (0..N байт, кратно 16).
     *
     * @throws ConnectionException При сбое openssl_decrypt
     */
    private function decryptIncomingBody(int $remaining): string
    {
        if ($remaining <= 0) {
            return '';
        }

        $restEnc = $this->readExact($remaining);
        $restPlain = openssl_decrypt(
            $restEnc,
            'aes-128-ctr',
            $this->serverToClientKey,
            OPENSSL_RAW_DATA,
            $this->serverToClientIv,
        );

        if ($restPlain === false) {
            throw new ConnectionException('SSH decrypt body не удался');
        }

        $this->serverToClientIv = $this->advanceCtr($this->serverToClientIv, (int) ($remaining / 16));

        return $restPlain;
    }

    /**
     * Закрывает TCP stream и обнуляет ссылку (повторный вызов безопасен).
     */
    public function close(): void
    {
        $stream = $this->stream;
        $this->stream = null;

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    /**
     * Пишет все байты в stream (цикл fwrite до полного отправления).
     *
     * @param string $data Полный кадр (plain или ciphertext+MAC)
     *
     * @throws ConnectionException При ошибке или нулевой записи
     */
    private function writeAll(string $data): void
    {
        $stream = $this->openStream();
        $offset = 0;
        $length = strlen($data);

        while ($offset < $length) {
            $written = @fwrite($stream, substr($data, $offset));

            if ($written === false || $written === 0) {
                throw new ConnectionException('SSH write не удался');
            }

            $offset += $written;
        }
    }

    /**
     * Читает ровно $length байт из stream.
     *
     * @param int $length Сколько байт нужно; при ≤0 цикл не выполняется
     *
     * @return string Ровно $length байт
     *
     * @throws ConnectionException При EOF или ошибке fread
     */
    private function readExact(int $length): string
    {
        $stream = $this->openStream();
        $buffer = '';

        while (strlen($buffer) < $length) {
            $need = $length - strlen($buffer);

            if ($need < 1) {
                break;
            }

            $chunk = @fread($stream, $need);

            if ($chunk === false || $chunk === '') {
                throw new ConnectionException('SSH read не удался / соединение закрыто');
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }

    /**
     * Возвращает открытый stream или бросает, если уже close().
     *
     * @return resource Открытый TCP stream
     *
     * @throws ConnectionException Если stream закрыт
     */
    private function openStream()
    {
        $stream = $this->stream;

        if (!is_resource($stream)) {
            throw new ConnectionException('SSH stream закрыт');
        }

        return $stream;
    }

    /**
     * Инкрементирует AES-CTR IV на указанное число блоков (big-endian counter по всем 16 байтам).
     *
     * @param string $counterIv Текущий IV (16 байт)
     * @param int    $blocks    Сколько блоков прибавить; при <1 возвращается исходный IV
     *
     * @return string Новый IV той же длины
     */
    private function advanceCtr(string $counterIv, int $blocks): string
    {
        if ($blocks < 1) {
            return $counterIv;
        }

        $value = $counterIv;

        for ($i = 0; $i < $blocks; ++$i) {
            for ($j = 15; $j >= 0; --$j) {
                $byte = (ord($value[$j]) + 1) & 0xFF;
                $value[$j] = chr($byte);

                if ($byte !== 0) {
                    break;
                }
            }
        }

        return $value;
    }
}

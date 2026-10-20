<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Driver\Ssh;

/**
 * Кодирование/декодирование SSH binary types (RFC 4251).
 */
final class SshBinary
{
    /**
     * uint32 big-endian.
     */
    public static function uint32(int $value): string
    {
        return pack('N', $value);
    }

    /**
     * Читает uint32.
     *
     * @param-out int $offset
     */
    public static function readUint32(string $buffer, int &$offset): int
    {
        $chunk = substr($buffer, $offset, 4);
        $offset += 4;
        $parts = unpack('Nval', $chunk);

        if ($parts === false || !array_key_exists('val', $parts)) {
            return 0;
        }

        if (is_int($parts['val'])) {
            return $parts['val'];
        }

        if (is_float($parts['val'])) {
            return (int) $parts['val'];
        }

        return 0;
    }

    /**
     * SSH string: uint32 length + bytes.
     */
    public static function sshString(string $value): string
    {
        return self::uint32(strlen($value)) . $value;
    }

    /**
     * Читает SSH string.
     *
     * @param-out int $offset
     */
    public static function readString(string $buffer, int &$offset): string
    {
        $length = self::readUint32($buffer, $offset);

        if ($length < 0 || $offset + $length > strlen($buffer)) {
            return '';
        }

        $value = substr($buffer, $offset, $length);
        $offset += $length;

        return $value;
    }

    /**
     * Name-list.
     *
     * @param list<string> $names
     */
    public static function nameList(array $names): string
    {
        return self::sshString(implode(',', $names));
    }

    /**
     * mpint из положительного бинарного big-endian.
     */
    public static function mpint(string $bytes): string
    {
        if ($bytes !== '' && (ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00" . $bytes;
        }

        return self::sshString($bytes);
    }

    /**
     * Читает mpint как бинарную строку без знака.
     *
     * @param-out int $offset
     */
    public static function readMpint(string $buffer, int &$offset): string
    {
        $raw = self::readString($buffer, $offset);

        if ($raw !== '' && $raw[0] === "\x00") {
            return substr($raw, 1);
        }

        return $raw;
    }
}

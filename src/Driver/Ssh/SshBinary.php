<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Driver\Ssh;

/**
 * Кодирование и декодирование бинарных типов SSH (RFC 4251).
 */
final class SshBinary
{
    /**
     * Кодирует беззнаковое 32-битное целое в big-endian.
     *
     * @param int $value Значение в диапазоне 0…2³²−1 (старшие биты обрезаются pack)
     *
     * @return string Ровно 4 байта
     */
    public static function uint32(int $value): string
    {
        return pack('N', $value);
    }

    /**
     * Читает uint32 из буфера и сдвигает смещение на 4 байта.
     *
     * @param string $buffer Исходный бинарный буфер
     * @param int    $offset Текущая позиция чтения; после вызова — следующая позиция
     *
     * @return int Прочитанное значение или 0 при повреждённых данных
     *
     * @param-out int $offset
     */
    public static function readUint32(string $buffer, int &$offset): int
    {
        $chunk = substr($buffer, $offset, 4);
        $offset += 4;
        $parts = unpack('Nval', $chunk);
        $value = 0;

        if (is_array($parts) && array_key_exists('val', $parts)) {
            if (is_int($parts['val'])) {
                $value = $parts['val'];
            } elseif (is_float($parts['val'])) {
                $value = (int) $parts['val'];
            }
        }

        return $value;
    }

    /**
     * Кодирует SSH string: uint32 длина + содержимое.
     *
     * @param string $value Произвольные байты (включая бинарные)
     *
     * @return string Закодированная строка
     */
    public static function sshString(string $value): string
    {
        return self::uint32(strlen($value)) . $value;
    }

    /**
     * Читает SSH string и сдвигает смещение.
     *
     * @param string $buffer Исходный бинарный буфер
     * @param int    $offset Текущая позиция чтения
     *
     * @return string Содержимое строки или '' при выходе за границы буфера
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
     * Кодирует name-list как SSH string со значениями через запятую.
     *
     * @param list<string> $names Имена алгоритмов/сервисов без запятых внутри
     *
     * @return string Закодированный name-list
     */
    public static function nameList(array $names): string
    {
        return self::sshString(implode(',', $names));
    }

    /**
     * Кодирует положительный mpint из big-endian байтов (с ведущим \x00 при старшем бите).
     *
     * @param string $bytes Беззнаковое big-endian представление числа
     *
     * @return string SSH mpint
     */
    public static function mpint(string $bytes): string
    {
        if ($bytes !== '' && (ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00" . $bytes;
        }

        return self::sshString($bytes);
    }

    /**
     * Читает mpint как беззнаковую бинарную строку (ведущий нулевой байт знака снимается).
     *
     * @param string $buffer Исходный бинарный буфер
     * @param int    $offset Текущая позиция чтения
     *
     * @return string Байты модуля без знакового префикса
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

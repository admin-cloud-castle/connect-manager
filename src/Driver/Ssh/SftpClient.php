<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Driver\Ssh;

use CloudCastle\ConnectManager\Exception\ConnectionException;

/**
 * Прикладной SFTP-клиент поверх нативной SSH-сессии.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 * @SuppressWarnings("PHPMD.ShortVariable")
 */
final class SftpClient
{
    private int $requestId = 0;

    /**
     * @param SftpChannelInterface $session Канал SFTP
     */
    public function __construct(private readonly SftpChannelInterface $session)
    {
    }

    /**
     * Читает удалённый файл.
     *
     * @throws ConnectionException
     */
    public function get(string $remotePath): string
    {
        $handle = $this->open($remotePath, 0x00000001);
        $content = '';
        $offset = 0;

        while (true) {
            $chunk = $this->readHandle($handle, $offset, 32_768);

            if ($chunk === '') {
                break;
            }

            $content .= $chunk;
            $offset += strlen($chunk);
        }

        $this->closeHandle($handle);

        return $content;
    }

    /**
     * Записывает удалённый файл.
     *
     * @throws ConnectionException
     */
    public function put(string $remotePath, string $contents): void
    {
        $handle = $this->open($remotePath, 0x0000001A);
        $offset = 0;
        $length = strlen($contents);

        while ($offset < $length) {
            $chunk = substr($contents, $offset, 32_768);
            $this->writeHandle($handle, $offset, $chunk);
            $offset += strlen($chunk);
        }

        $this->closeHandle($handle);
    }

    /**
     * Список файлов каталога.
     *
     * @return list<string>
     *
     * @throws ConnectionException
     */
    public function list(string $remotePath = '.'): array
    {
        $handle = $this->openDir($remotePath);
        $names = [];

        while (true) {
            $batch = $this->readDir($handle);

            if ($batch === []) {
                break;
            }

            foreach ($batch as $name) {
                $names[] = $name;
            }
        }

        $this->closeHandle($handle);

        return $names;
    }

    /**
     * Создаёт каталог.
     *
     * @throws ConnectionException
     */
    public function mkdir(string $remotePath): void
    {
        $id = ++$this->requestId;
        $this->session->sendSftp(
            chr(14) . SshBinary::uint32($id) . SshBinary::sshString($remotePath) . SshBinary::uint32(0x000001FF),
        );
        $this->expectStatus($id);
    }

    /**
     * Удаляет файл.
     *
     * @throws ConnectionException
     */
    public function delete(string $remotePath): void
    {
        $id = ++$this->requestId;
        $this->session->sendSftp(chr(13) . SshBinary::uint32($id) . SshBinary::sshString($remotePath));
        $this->expectStatus($id);
    }

    /**
     * Проверяет существование пути.
     */
    public function exists(string $remotePath): bool
    {
        try {
            $id = ++$this->requestId;
            $this->session->sendSftp(chr(17) . SshBinary::uint32($id) . SshBinary::sshString($remotePath));
            $packet = $this->session->receiveSftp();

            return $packet !== '' && ord($packet[0]) === 105;
        } catch (ConnectionException) {
            return false;
        }
    }

    /**
     * Низкоуровневый SFTP-канал.
     */
    public function resource(): SftpChannelInterface
    {
        return $this->session;
    }

    /**
     * @throws ConnectionException
     */
    private function open(string $path, int $flags): string
    {
        $id = ++$this->requestId;
        $this->session->sendSftp(
            chr(3)
            . SshBinary::uint32($id)
            . SshBinary::sshString($path)
            . SshBinary::uint32($flags)
            . SshBinary::uint32(0),
        );
        $packet = $this->session->receiveSftp();

        if ($packet === '' || ord($packet[0]) !== 102) {
            throw new ConnectionException('SFTP OPEN не удался: ' . $path);
        }

        $offset = 1;
        SshBinary::readUint32($packet, $offset);

        return SshBinary::readString($packet, $offset);
    }

    /**
     * @throws ConnectionException
     */
    private function openDir(string $path): string
    {
        $id = ++$this->requestId;
        $this->session->sendSftp(chr(11) . SshBinary::uint32($id) . SshBinary::sshString($path));
        $packet = $this->session->receiveSftp();

        if ($packet === '' || ord($packet[0]) !== 102) {
            throw new ConnectionException('SFTP OPENDIR не удался: ' . $path);
        }

        $offset = 1;
        SshBinary::readUint32($packet, $offset);

        return SshBinary::readString($packet, $offset);
    }

    /**
     * @throws ConnectionException
     */
    private function readHandle(string $handle, int $offset, int $length): string
    {
        $id = ++$this->requestId;
        $this->session->sendSftp(
            chr(5)
            . SshBinary::uint32($id)
            . SshBinary::sshString($handle)
            . $this->uint64($offset)
            . SshBinary::uint32($length),
        );
        $packet = $this->session->receiveSftp();

        if ($packet === '') {
            return '';
        }

        $type = ord($packet[0]);

        if ($type === 101) {
            return '';
        }

        if ($type !== 103) {
            throw new ConnectionException('SFTP READ не удался');
        }

        $cursor = 1;
        SshBinary::readUint32($packet, $cursor);

        return SshBinary::readString($packet, $cursor);
    }

    /**
     * @throws ConnectionException
     */
    private function writeHandle(string $handle, int $offset, string $data): void
    {
        $id = ++$this->requestId;
        $this->session->sendSftp(
            chr(6)
            . SshBinary::uint32($id)
            . SshBinary::sshString($handle)
            . $this->uint64($offset)
            . SshBinary::sshString($data),
        );
        $this->expectStatus($id);
    }

    /**
     * @return list<string>
     *
     * @throws ConnectionException
     */
    private function readDir(string $handle): array
    {
        $id = ++$this->requestId;
        $this->session->sendSftp(chr(12) . SshBinary::uint32($id) . SshBinary::sshString($handle));
        $packet = $this->session->receiveSftp();

        if ($packet === '') {
            return [];
        }

        $type = ord($packet[0]);

        if ($type === 101) {
            return [];
        }

        if ($type !== 104) {
            throw new ConnectionException('SFTP READDIR не удался');
        }

        $cursor = 1;
        SshBinary::readUint32($packet, $cursor);
        $count = SshBinary::readUint32($packet, $cursor);
        $names = [];

        for ($i = 0; $i < $count; ++$i) {
            $names[] = SshBinary::readString($packet, $cursor);
            SshBinary::readString($packet, $cursor);
            SshBinary::readUint32($packet, $cursor);
            $cursor += 4 + 8 + 8 + 4 + 4 + 4 + 4 + 4;
        }

        return $names;
    }

    /**
     * @throws ConnectionException
     */
    private function closeHandle(string $handle): void
    {
        $id = ++$this->requestId;
        $this->session->sendSftp(chr(4) . SshBinary::uint32($id) . SshBinary::sshString($handle));
        $this->expectStatus($id);
    }

    /**
     * @throws ConnectionException
     */
    private function expectStatus(int $requestId): void
    {
        $packet = $this->session->receiveSftp();

        if ($packet === '' || ord($packet[0]) !== 101) {
            throw new ConnectionException('Ожидался SSH_FXP_STATUS');
        }

        $offset = 1;
        $id = SshBinary::readUint32($packet, $offset);
        $code = SshBinary::readUint32($packet, $offset);

        if ($id !== $requestId || $code !== 0) {
            throw new ConnectionException('SFTP status error code=' . $code);
        }
    }

    private function uint64(int $value): string
    {
        $hi = intdiv($value, 4_294_967_296);
        $lo = $value % 4_294_967_296;

        return SshBinary::uint32($hi) . SshBinary::uint32($lo);
    }
}

<?php

namespace App\Services;

use App\Models\SiteOption;
use RuntimeException;

class MinecraftRconService
{
    private const TYPE_RESPONSE_VALUE = 0;

    private const TYPE_COMMAND = 2;

    private const TYPE_AUTH = 3;

    private const TYPE_AUTH_RESPONSE = 2;

    private const MAX_PACKET_BYTES = 4_194_304;

    private const MAX_RESPONSE_PACKETS = 128;

    public function execute(string $command): string
    {
        $command = trim($command);
        if ($command === '') {
            throw new RuntimeException('Command is required.');
        }

        [$host, $port, $password, $timeoutSeconds] = $this->connectionConfig();

        $socket = @fsockopen($host, $port, $errorNumber, $errorString, $timeoutSeconds);
        if (! is_resource($socket)) {
            throw new RuntimeException(sprintf(
                'Unable to connect to RCON server at %s:%d (%s).',
                $host,
                $port,
                trim($errorString) !== '' ? $errorString : 'connection failed'
            ));
        }

        stream_set_timeout($socket, (int) ceil($timeoutSeconds));

        try {
            $this->authenticate($socket, $password);

            $requestId = random_int(1, 2_000_000_000);
            $this->writePacket($socket, $requestId, self::TYPE_COMMAND, $command);

            $chunks = [];
            $packetCount = 0;
            $shortTimeoutApplied = false;
            while ($packetCount < self::MAX_RESPONSE_PACKETS) {
                $packet = $this->readPacket($socket);
                if ($packet === null) {
                    break;
                }

                $packetCount++;
                if (! $shortTimeoutApplied) {
                    // First response received; only wait briefly for any trailing packets.
                    stream_set_timeout($socket, 0, 200_000);
                    $shortTimeoutApplied = true;
                }

                if (
                    $packet['id'] === $requestId
                    && in_array($packet['type'], [self::TYPE_RESPONSE_VALUE, self::TYPE_COMMAND], true)
                ) {
                    $chunks[] = $packet['body'];
                }
            }

            return trim(implode('', $chunks));
        } finally {
            fclose($socket);
        }
    }

    /**
     * @return array{0: string, 1: int, 2: string, 3: float}
     */
    private function connectionConfig(): array
    {
        $host = trim((string) SiteOption::value('minecraft.rcon-host', SiteOption::defaultValue('minecraft.rcon-host')));
        $port = (int) SiteOption::value('minecraft.rcon-port', SiteOption::defaultValue('minecraft.rcon-port'));
        $password = trim((string) SiteOption::value('minecraft.rcon-password', SiteOption::defaultValue('minecraft.rcon-password')));
        $timeoutSeconds = (float) SiteOption::value('minecraft.rcon-timeout-seconds', SiteOption::defaultValue('minecraft.rcon-timeout-seconds'));

        if ($host === '') {
            throw new RuntimeException('RCON host is not configured. Set minecraft.rcon-host in Site Options.');
        }

        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('RCON port is invalid. Set minecraft.rcon-port to a value between 1 and 65535.');
        }

        if ($password === '') {
            throw new RuntimeException('RCON password is not configured. Set minecraft.rcon-password in Site Options.');
        }

        if ($timeoutSeconds <= 0) {
            $timeoutSeconds = 5.0;
        }

        return [$host, $port, $password, $timeoutSeconds];
    }

    /**
     * @param  resource  $socket
     */
    private function authenticate($socket, string $password): void
    {
        $authId = random_int(1, 2_000_000_000);
        $this->writePacket($socket, $authId, self::TYPE_AUTH, $password);

        $packetCount = 0;
        while ($packetCount < 8) {
            $packet = $this->readPacket($socket);
            if ($packet === null) {
                break;
            }

            $packetCount++;
            if ($packet['type'] !== self::TYPE_AUTH_RESPONSE) {
                continue;
            }

            if ($packet['id'] === -1) {
                throw new RuntimeException('RCON authentication failed. Check minecraft.rcon-password.');
            }

            if ($packet['id'] === $authId) {
                return;
            }
        }

        throw new RuntimeException('RCON authentication response was not received.');
    }

    /**
     * @param  resource  $socket
     */
    private function writePacket($socket, int $id, int $type, string $body): void
    {
        $payload = pack('V', $id).pack('V', $type).$body."\x00\x00";
        $packet = pack('V', strlen($payload)).$payload;

        $written = 0;
        $length = strlen($packet);
        while ($written < $length) {
            $result = fwrite($socket, substr($packet, $written));
            if ($result === false || $result === 0) {
                throw new RuntimeException('Failed to write RCON packet.');
            }

            $written += $result;
        }
    }

    /**
     * @param  resource  $socket
     * @return array{id: int, type: int, body: string}|null
     */
    private function readPacket($socket): ?array
    {
        $lengthBinary = $this->readExact($socket, 4, true);
        if ($lengthBinary === null) {
            return null;
        }

        $length = unpack('V', $lengthBinary)[1] ?? 0;
        if ($length < 10 || $length > self::MAX_PACKET_BYTES) {
            throw new RuntimeException('Received invalid RCON packet length.');
        }

        $packet = $this->readExact($socket, $length, false);
        if ($packet === null || strlen($packet) < 10) {
            throw new RuntimeException('RCON connection closed while reading packet.');
        }

        $id = $this->toSignedInt(unpack('V', substr($packet, 0, 4))[1] ?? 0);
        $type = $this->toSignedInt(unpack('V', substr($packet, 4, 4))[1] ?? 0);
        $body = substr($packet, 8, -2);

        return [
            'id' => $id,
            'type' => $type,
            'body' => $body === false ? '' : $body,
        ];
    }

    /**
     * @param  resource  $socket
     */
    private function readExact($socket, int $length, bool $allowEmpty): ?string
    {
        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($socket, $length - strlen($data));
            if ($chunk === false) {
                $meta = stream_get_meta_data($socket);
                if (($meta['timed_out'] ?? false) === true || ($meta['eof'] ?? false) === true) {
                    if ($allowEmpty && $data === '') {
                        return null;
                    }
                }

                throw new RuntimeException('Failed reading from RCON socket.');
            }

            if ($chunk === '') {
                $meta = stream_get_meta_data($socket);
                if (($meta['timed_out'] ?? false) === true) {
                    if ($allowEmpty && $data === '') {
                        return null;
                    }

                    throw new RuntimeException('RCON connection timed out while waiting for data.');
                }

                if (($meta['eof'] ?? false) === true) {
                    if ($allowEmpty && $data === '') {
                        return null;
                    }

                    throw new RuntimeException('RCON connection closed unexpectedly.');
                }

                continue;
            }

            $data .= $chunk;
        }

        return $data;
    }

    private function toSignedInt(int $value): int
    {
        if ($value > 0x7FFFFFFF) {
            return $value - 0x100000000;
        }

        return $value;
    }
}

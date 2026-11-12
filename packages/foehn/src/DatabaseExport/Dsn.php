<?php

declare(strict_types=1);

namespace Studiometa\Foehn\DatabaseExport;

/**
 * The PDO DSN for the `DB_HOST` and `DB_NAME` WordPress connects with.
 */
final readonly class Dsn
{
    /**
     * `DB_HOST` can be `host`, `host:port`, `host:/path/to/socket`, `[ipv6]:port` or
     * a socket path alone, as `wpdb::parse_db_host()` reads it.
     */
    public static function fromWordPress(string $host, string $database): string
    {
        $matches = [];
        preg_match('/^(?:\[(?<ipv6>[^\]]+)\]|(?<host>[^:\/]*))(?::(?<port>\d+)|:?(?<socket>\/.*))?$/', $host, $matches);

        $parts = ['dbname=' . $database];
        $socket = $matches['socket'] ?? '';
        $hostname = ($matches['ipv6'] ?? '') !== '' ? $matches['ipv6'] : $matches['host'] ?? '';
        $port = $matches['port'] ?? '';

        if ($socket !== '') {
            $parts[] = 'unix_socket=' . $socket;
        }

        if ($hostname !== '') {
            $parts[] = 'host=' . $hostname;
        }

        if ($port !== '') {
            $parts[] = 'port=' . $port;
        }

        return 'mysql:' . implode(';', $parts);
    }
}

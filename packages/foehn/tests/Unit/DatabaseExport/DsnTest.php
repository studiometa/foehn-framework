<?php

declare(strict_types=1);

use Studiometa\Foehn\DatabaseExport\Dsn;

describe('Dsn', function () {
    it('reads every form of DB_HOST', function (string $host, string $dsn) {
        expect(Dsn::fromWordPress($host, 'wordpress'))->toBe($dsn);
    })->with([
        ['db', 'mysql:dbname=wordpress;host=db'],
        ['localhost:3307', 'mysql:dbname=wordpress;host=localhost;port=3307'],
        [
            'localhost:/run/mysqld/mysqld.sock',
            'mysql:dbname=wordpress;unix_socket=/run/mysqld/mysqld.sock;host=localhost',
        ],
        ['/run/mysqld/mysqld.sock', 'mysql:dbname=wordpress;unix_socket=/run/mysqld/mysqld.sock'],
        ['[::1]:3306', 'mysql:dbname=wordpress;host=::1;port=3306'],
        ['[::1]', 'mysql:dbname=wordpress;host=::1'],
    ]);
});

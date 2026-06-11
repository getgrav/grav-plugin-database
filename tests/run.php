<?php

require_once __DIR__ . '/../classes/PDO.php';
require_once __DIR__ . '/../classes/Database.php';

use Grav\Plugin\Database\Database;

final class CapturingDatabase extends Database
{
    public $connection;

    public function __construct(array $connections)
    {
        $property = new ReflectionProperty(Database::class, 'connections');
        $property->setValue($this, $connections);
    }

    public function connect($dsn, $username = '', $password = '', $options = [])
    {
        $this->connection = [
            'dsn' => $dsn,
            'username' => $username,
            'password' => $password,
            'options' => $options,
        ];

        return $this->connection;
    }
}

function assertSameValue($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function testPgsqlNamedConnectionBuildsDsn()
{
    if (!in_array('pgsql', PDO::getAvailableDrivers(), true)) {
        fwrite(STDOUT, "Skipped PostgreSQL DSN test because pdo_pgsql is unavailable\n");
        return;
    }

    $database = new CapturingDatabase([
        'pgsql' => [
            [
                'name' => 'page_views',
                'host' => 'db.example.test',
                'port' => 5432,
                'dbname' => 'grav',
                'user' => 'grav',
                'password' => 'secret',
                'sslmode' => 'require',
            ],
        ],
    ]);

    $database->pgsql('page_views');

    assertSameValue(
        'pgsql:host=db.example.test;port=5432;dbname=grav;user=grav;password=secret;sslmode=require',
        $database->connection['dsn'],
        'PostgreSQL named connections should preserve the sslmode DSN key.'
    );
    assertSameValue('grav', $database->connection['username'], 'PostgreSQL username should be passed to PDO.');
    assertSameValue('secret', $database->connection['password'], 'PostgreSQL password should be passed to PDO.');
}

testPgsqlNamedConnectionBuildsDsn();
fwrite(STDOUT, "All database plugin tests passed\n");

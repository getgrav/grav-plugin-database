<?php

require_once __DIR__ . '/../classes/PDO.php';
require_once __DIR__ . '/../classes/Database.php';

use Grav\Plugin\Database\Database;

/**
 * Test double that captures the generated DSN instead of opening a real
 * connection, and injects connection config without booting Grav.
 */
final class CapturingDatabase extends Database
{
    public $connection;

    public function __construct(array $connections)
    {
        $property = new ReflectionProperty(Database::class, 'connections');
        // Required for PHP < 8.1; a harmless no-op on newer versions.
        $property->setAccessible(true);
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
        // Loud skip: a missing driver must not read as a passing assertion.
        fwrite(STDERR, "WARNING: skipped PostgreSQL DSN test because pdo_pgsql is unavailable\n");
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

function testPgsqlUnsetSslmodeFallsBackToPrefer()
{
    if (!in_array('pgsql', PDO::getAvailableDrivers(), true)) {
        fwrite(STDERR, "WARNING: skipped PostgreSQL sslmode fallback test because pdo_pgsql is unavailable\n");
        return;
    }

    $database = new CapturingDatabase([
        'pgsql' => [
            [
                'name' => 'no_ssl',
                'host' => 'db.example.test',
                'port' => 5432,
                'dbname' => 'grav',
                'user' => 'grav',
                'password' => 'secret',
                // sslmode intentionally omitted
            ],
        ],
    ]);

    $database->pgsql('no_ssl');

    assertSameValue(
        'pgsql:host=db.example.test;port=5432;dbname=grav;user=grav;password=secret;sslmode=prefer',
        $database->connection['dsn'],
        'An unset PostgreSQL sslmode should fall back to "prefer" without warnings.'
    );
}

function testSqlsrvEncryptDsn()
{
    if (!in_array('sqlsrv', PDO::getAvailableDrivers(), true)) {
        fwrite(STDERR, "WARNING: skipped SQL Server DSN test because pdo_sqlsrv is unavailable\n");
        return;
    }

    $database = new CapturingDatabase([
        'sqlsrv' => [
            [
                'name' => 'reports',
                'server' => 'mssql.example.test',
                'port' => 1433,
                'database' => 'grav',
                'username' => 'grav',
                'password' => 'secret',
                'encrypt' => true,
            ],
            [
                'name' => 'plain',
                'server' => 'mssql.example.test',
                'port' => 1433,
                'database' => 'grav',
                'username' => 'grav',
                'password' => 'secret',
                'encrypt' => false,
            ],
        ],
    ]);

    $database->sqlsrv('reports');
    assertSameValue(
        'sqlsrv:Server=mssql.example.test,1433;Database=grav;Encrypt=true',
        $database->connection['dsn'],
        'SQL Server should emit Encrypt=true when encryption is enabled.'
    );

    $database->sqlsrv('plain');
    assertSameValue(
        'sqlsrv:Server=mssql.example.test,1433;Database=grav;Encrypt=false',
        $database->connection['dsn'],
        'SQL Server should emit Encrypt=false when encryption is disabled.'
    );
}

testPgsqlNamedConnectionBuildsDsn();
testPgsqlUnsetSslmodeFallsBackToPrefer();
testSqlsrvEncryptDsn();
fwrite(STDOUT, "All database plugin tests passed\n");

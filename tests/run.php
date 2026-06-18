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

    public function connect($dsn, $username = '', $password = '', $options = [], $class = null)
    {
        $this->connection = [
            'dsn' => $dsn,
            'username' => $username,
            'password' => $password,
            'options' => $options,
            'class' => $class,
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
        'pgsql:host=db.example.test;port=5432;dbname=grav;sslmode=require',
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
        'pgsql:host=db.example.test;port=5432;dbname=grav;sslmode=prefer',
        $database->connection['dsn'],
        'An unset PostgreSQL sslmode should fall back to "prefer" without warnings.'
    );
}

function testPgsqlPasswordWithSemicolonIsNotInDsn()
{
    if (!in_array('pgsql', PDO::getAvailableDrivers(), true)) {
        fwrite(STDERR, "WARNING: skipped PostgreSQL password test because pdo_pgsql is unavailable\n");
        return;
    }

    $database = new CapturingDatabase([
        'pgsql' => [
            ['name' => 'pw', 'host' => 'db', 'port' => 5432, 'dbname' => 'grav', 'user' => 'grav', 'password' => 'pa;ss;word'],
        ],
    ]);

    $database->pgsql('pw');

    assertSameValue(false, strpos($database->connection['dsn'], 'pa;ss;word'), 'A password with semicolons must never be embedded in the DSN.');
    assertSameValue('pa;ss;word', $database->connection['password'], 'A password with semicolons must still be passed to PDO unchanged.');
}

function testDsnAttributeInjectionIsRejected()
{
    $cases = [
        ['mysql', ['name' => 'x', 'host' => '127.0.0.1;unix_socket=/tmp/evil.sock', 'port' => 3306, 'dbname' => 'grav', 'charset' => 'utf8mb4', 'username' => 'u', 'password' => 'p'], 'host'],
        ['sqlsrv', ['name' => 'x', 'server' => 'mssql;TrustServerCertificate=true', 'port' => 1433, 'database' => 'grav', 'encrypt' => false, 'username' => 'u', 'password' => 'p'], 'server'],
    ];

    foreach ($cases as [$driver, $connection, $field]) {
        if (!in_array($driver, PDO::getAvailableDrivers(), true)) {
            fwrite(STDERR, "WARNING: skipped {$driver} injection test because the driver is unavailable\n");
            continue;
        }

        $database = new CapturingDatabase([$driver => [$connection]]);

        $threw = false;
        try {
            $database->{$driver}('x');
        } catch (\RuntimeException $e) {
            $threw = true;
        }
        assertTrueValue($threw, "A ';' injected into the {$driver} '{$field}' field must be rejected.");
    }
}

function testPortIsCastToInteger()
{
    if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
        fwrite(STDERR, "WARNING: skipped port-cast test because pdo_mysql is unavailable\n");
        return;
    }

    $database = new CapturingDatabase([
        'mysql' => [
            ['name' => 'x', 'host' => 'db', 'port' => '3306; DROP', 'dbname' => 'grav', 'charset' => 'utf8mb4', 'username' => 'u', 'password' => 'p'],
        ],
    ]);

    $database->mysql('x');
    assertSameValue(
        'mysql:host=db;port=3306;dbname=grav;charset=utf8mb4',
        $database->connection['dsn'],
        'A non-numeric port must be cast to an integer, dropping any injected suffix.'
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

function assertTrueValue($actual, $message)
{
    if ($actual !== true) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: true' . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assertFalseValue($actual, $message)
{
    if ($actual !== false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: false' . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function testTableExistsIsNotSqlInjectable()
{
    if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
        fwrite(STDERR, "WARNING: skipped tableExists injection test because pdo_sqlite is unavailable\n");
        return;
    }

    $pdo = new \Grav\Plugin\Database\PDO('sqlite::memory:');
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE real_table (id INTEGER)');

    assertTrueValue($pdo->tableExists('real_table'), 'tableExists should find a real table.');
    assertFalseValue($pdo->tableExists('missing_table'), 'tableExists should not find a missing table.');

    // An attempt to break out of the catalog lookup must not execute and must
    // report the table as non-existent (GHSA-8jxg-4pw9-xcwf).
    $payload = 'real_table; CREATE TABLE injected (id INTEGER); --';
    assertFalseValue($pdo->tableExists($payload), 'tableExists must treat an injection payload as a non-existent table.');

    $union = 'real_table UNION SELECT sql FROM sqlite_master --';
    assertFalseValue($pdo->tableExists($union), 'tableExists must not allow a UNION-based exfiltration payload to resolve.');

    $injected = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='injected'")->fetchColumn();
    assertFalseValue($injected, 'The injection payload must not have created the "injected" table.');
}

function testRegisteredDriverBuildsDsnAndUsesItsClass()
{
    $database = new CapturingDatabase([
        'fauxdb' => [
            [
                'name' => 'app',
                'directory' => 'user/data',
                'filename' => 'app.fdb',
            ],
        ],
    ]);

    // A third-party plugin registers its driver via the onDatabaseDrivers event.
    $database->registerDriver('fauxdb', [
        'label' => 'FauxDB',
        'class' => '\\Grav\\Plugin\\Database\\PDO',
        'dsn' => function (array $connection) {
            return 'fauxdb:' . $connection['directory'] . '/' . $connection['filename'];
        },
    ]);

    assertSameValue(['fauxdb' => $database->getDrivers()['fauxdb']], $database->getDrivers(), 'Registered driver should be retrievable via getDrivers().');

    $database->fauxdb('app');
    assertSameValue(
        'fauxdb:user/data/app.fdb',
        $database->connection['dsn'],
        'A registered driver should build its DSN from its own dsn callable.'
    );
    assertSameValue(
        '\\Grav\\Plugin\\Database\\PDO',
        $database->connection['class'],
        'A registered driver should connect using its declared class.'
    );
}

function testRegisteredDriverCredentialsCallable()
{
    $database = new CapturingDatabase([
        'fauxdb' => [
            ['name' => 'app', 'user' => 'bob', 'secret' => 'hunter2'],
        ],
    ]);

    $database->registerDriver('fauxdb', [
        'dsn' => function (array $c) {
            return 'fauxdb:app';
        },
        'credentials' => function (array $c) {
            return [$c['user'], $c['secret']];
        },
    ]);

    $database->fauxdb('app');
    assertSameValue('bob', $database->connection['username'], 'Registered driver credentials callable should supply the username.');
    assertSameValue('hunter2', $database->connection['password'], 'Registered driver credentials callable should supply the password.');
}

testPgsqlNamedConnectionBuildsDsn();
testPgsqlUnsetSslmodeFallsBackToPrefer();
testPgsqlPasswordWithSemicolonIsNotInDsn();
testSqlsrvEncryptDsn();
testTableExistsIsNotSqlInjectable();
testDsnAttributeInjectionIsRejected();
testPortIsCastToInteger();
testRegisteredDriverBuildsDsnAndUsesItsClass();
testRegisteredDriverCredentialsCallable();
fwrite(STDOUT, "All database plugin tests passed\n");

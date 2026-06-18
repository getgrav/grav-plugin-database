<?php

namespace Grav\Plugin\Database;

use Grav\Common\Grav;

class Database
{
    private $dsn_array = [];
    private $connections;

    /**
     * Drivers registered by third-party plugins via the `onDatabaseDrivers`
     * event. Each entry is keyed by connection type and holds:
     *   - label:       string shown in the admin connections form
     *   - dsn:         callable(array $connection): string
     *   - class:       PDO-compatible class to instantiate (uses StatementHelpers)
     *   - credentials: optional callable(array $connection): array [user, pass]
     *   - blueprint:   optional fieldset embedded into the connections form
     *
     * @var array
     */
    private $drivers = [];

    public function __construct()
    {
        $grav = Grav::instance();
        $config = $grav['config'];
        $this->connections = $config->get('plugins.database.connections');
    }

    /**
     * Register a third-party connection driver. Called by plugins responding to
     * the `onDatabaseDrivers` event, e.g. `$grav['database']->registerDriver(...)`.
     *
     * Drivers build their own DSN via their `dsn` callable, so they are
     * responsible for validating their own connection values (e.g. rejecting
     * ';' or line breaks) the same way the built-in drivers do.
     */
    public function registerDriver($name, array $definition)
    {
        $this->drivers[$name] = $definition;

        return $this;
    }

    /**
     * @return array all registered third-party drivers, keyed by name.
     */
    public function getDrivers()
    {
        return $this->drivers;
    }

    /**
     * Dynamic options for the admin "connection driver" select. Lists the
     * built-in PDO drivers plus any registered by third-party plugins, so an
     * add-on engine (e.g. the database-yetisql plugin) shows up automatically.
     *
     * Referenced from blueprints.yaml via `data-options@`.
     *
     * @return array value => label
     */
    public static function driverOptions()
    {
        $options = [
            'mysql' => 'PLUGIN_DATABASE.DRIVER_MYSQL',
            'pgsql' => 'PLUGIN_DATABASE.DRIVER_PGSQL',
            'sqlite' => 'PLUGIN_DATABASE.DRIVER_SQLITE',
            'sqlsrv' => 'PLUGIN_DATABASE.DRIVER_SQLSRV',
        ];

        $grav = Grav::instance();
        if (isset($grav['database'])) {
            foreach ($grav['database']->getDrivers() as $name => $definition) {
                $options[$name] = $definition['label'] ?? $name;
            }
        }

        return $options;
    }

    public function __call($method, $args)
    {
        $registered = $this->drivers[$method] ?? null;

        if ($registered === null && !\in_array($method, PDO::getAvailableDrivers())) {
            throw new \RuntimeException(
                'PHP PDO extension for ' .
                    $method .
                    ' is not installed/configured.'
            );
        }
        if (!\in_array($method, \array_keys((array) $this->connections))) {
            throw new \RuntimeException(
                'There are no ' .
                    $method .
                    ' connection configurations defined.'
            );
        }
        $connection = $this->getNamedConnection($method, $args[0]);
        if (!$connection) {
            throw new \RuntimeException(
                'There is no ' .
                    $method .
                    ' connection configuration named ' .
                    $args[0]
            );
        }

        // Third-party drivers build their own DSN and supply their own class.
        if ($registered !== null) {
            $dsn = ($registered['dsn'])($connection);
            $credentials = isset($registered['credentials'])
                ? ($registered['credentials'])($connection)
                : ['', ''];

            return $this->connect(
                $dsn,
                $credentials[0] ?? '',
                $credentials[1] ?? '',
                $this->options(),
                $registered['class'] ?? PDO::class
            );
        }

        $dsn = $method . ':';
        switch ($method) {
            case 'mysql':
                $dsn .= 'host=' . $this->dsnValue($connection['host'], 'host');
                $dsn .= ';port=' . (int) $connection['port'];
                $dsn .= ';dbname=' . $this->dsnValue($connection['dbname'], 'dbname');
                $dsn .= ';charset=' . $this->dsnValue($connection['charset'], 'charset');
                $username = $connection['username'];
                $password = $connection['password'];
                break;
            case 'pgsql':
                // user/password are passed to PDO as arguments (below), not
                // embedded in the DSN: that keeps passwords containing ';' valid
                // and removes the DSN-attribute injection vector.
                $dsn .= 'host=' . $this->dsnValue($connection['host'], 'host');
                $dsn .= ';port=' . (int) $connection['port'];
                $dsn .= ';dbname=' . $this->dsnValue($connection['dbname'], 'dbname');
                $dsn .=
                    ';sslmode=' .
                    (\in_array($connection['sslmode'] ?? null, [
                        'disable',
                        'allow',
                        'prefer',
                        'require',
                        'verify-ca',
                        'verify-full',
                    ], true)
                        ? $connection['sslmode']
                        : 'prefer');
                $username = $connection['user'];
                $password = $connection['password'];
                break;
            case 'sqlite':
                $dsn .= $this->dsnPath($connection['directory'], 'directory');
                $dsn .= '/';
                $dsn .= $this->dsnPath($connection['filename'], 'filename');
                $username = '';
                $password = '';
                break;
            case 'sqlsrv':
                $dsn .= 'Server=' . $this->dsnValue($connection['server'], 'server');
                $dsn .= ',' . (int) $connection['port'];
                $dsn .= ';Database=' . $this->dsnValue($connection['database'], 'database');
                $dsn .= ';Encrypt=' . (!empty($connection['encrypt']) ? 'true' : 'false');
                $username = $connection['username'];
                $password = $connection['password'];
                break;
            default:
                throw new \RuntimeException(
                    $method . ' is not a valid connection type.'
                );
                break;
        }

        return $this->connect($dsn, $username, $password, $this->options());
    }

    /**
     * Guard a DSN attribute value. A ';' (or a line break / null byte) has no
     * legitimate place in a host, dbname, charset, server or database name, and
     * would let a configured value smuggle extra DSN attributes. Fail closed.
     */
    private function dsnValue($value, $field)
    {
        if (\preg_match('/[;\r\n\x00]/', (string) $value)) {
            throw new \RuntimeException(
                "Invalid character in database connection setting '{$field}'."
            );
        }

        return (string) $value;
    }

    /**
     * Guard a SQLite path component. Line breaks and null bytes are never valid
     * in a path; the directory itself is an intentional admin choice, so it is
     * otherwise left as-is.
     */
    private function dsnPath($value, $field)
    {
        if (\preg_match('/[\r\n\x00]/', (string) $value)) {
            throw new \RuntimeException(
                "Invalid character in database connection setting '{$field}'."
            );
        }

        return (string) $value;
    }

    public function connect($dsn, $username = '', $password = '', $options = [], $class = null)
    {
        if (!\array_key_exists($dsn, $this->dsn_array)) {
            $class = $class ?: PDO::class;
            $this->dsn_array[$dsn] = new $class(
                $dsn,
                $username,
                $password,
                $options
            );
        }
        return $this->dsn_array[$dsn];
    }

    private function options()
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }

    private function getNamedConnection($type, $name)
    {
        return \array_reduce($this->connections[$type], function (
            $state,
            $item
        ) use ($name) {
            if ($item['name'] !== $name) {
                return $state;
            }
            if ($state === null) {
                return $item;
            }
            return $state;
        });
    }
}

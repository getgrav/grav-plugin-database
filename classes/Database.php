<?php

namespace Grav\Plugin\Database;

use Grav\Common\Grav;

class Database
{
    private $dsn_array = [];
    private $connections;

    public function __construct()
    {
        $grav = Grav::instance();
        $config = $grav['config'];
        $this->connections = $config->get('plugins.database.connections');
    }

    public function __call($method, $args)
    {
        if (!\in_array($method, PDO::getAvailableDrivers())) {
            throw new \RuntimeException(
                'PHP PDO extension for ' .
                    $method .
                    ' is not installed/configured.'
            );
        }
        if (!\in_array($method, \array_keys($this->connections))) {
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

        $dsn = $method . ':';
        switch ($method) {
            case 'mysql':
                $dsn .= 'host=' . $connection['host'];
                $dsn .= ';port=' . $connection['port'];
                $dsn .= ';dbname=' . $connection['dbname'];
                $dsn .= ';charset=' . $connection['charset'];
                $username = $connection['username'];
                $password = $connection['password'];
                break;
            case 'pgsql':
                $dsn .= 'host=' . $connection['host'];
                $dsn .= ';port=' . $connection['port'];
                $dsn .= ';dbname=' . $connection['dbname'];
                $dsn .= ';user=' . $connection['user'];
                $dsn .= ';password=' . $connection['password'];
                $dsn .=
                    ';sslmode=' .
                    \in_array($connection['sslmode'], [
                        'disable',
                        'allow',
                        'prefer',
                        'require',
                        'verify-ca',
                        'verify-full',
                    ])
                        ? $connection['sslmode']
                        : 'prefer';
                $username = $connection['user'];
                $password = $connection['password'];
                break;
            case 'sqlite':
                $dsn .= $connection['directory'];
                $dsn .= '/';
                $dsn .= $connection['filename'];
                $username = '';
                $password = '';
                break;
            case 'sqlsrv':
                $dsn .= 'Server=' . $connection['server'];
                $dsn .= ',' . $connection['port'];
                $dsn .= ';Database=' . $connection['database'];
                $dsn .= ';Encrypt=' . $connection['encrypt'] ? 'true' : 'false';
                $username = $connection['username'];
                $password = $connection['password'];
                break;
            default:
                throw new \RuntimeException(
                    $method . ' is not a valid connection type.'
                );
                break;
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        return $this->connect($dsn, $username, $password, $options);
    }

    public function connect($dsn, $username = '', $password = '', $options = [])
    {
        if (!\array_key_exists($dsn, $this->dsn_array)) {
            $this->dsn_array[$dsn] = new PDO(
                $dsn,
                $username,
                $password,
                $options
            );
        }
        return $this->dsn_array[$dsn];
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

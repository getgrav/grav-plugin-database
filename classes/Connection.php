<?php

namespace Grav\Plugin\Database;

use Grav\Plugin\Database\Drivers\MYSQLDriver;
use Grav\Plugin\Database\Drivers\SQLITEDriver;
use Grav\Plugin\Database\Drivers\SQLSRVDriver;

class Connection
{
    protected $connections;

    public function __construct($connections)
    {
        $this->connections = $connections;
    }

    public function __call($method, $args)
    {
        if (!\in_array($method, ["mysql", "sqlite", "sqlsrv"])) {
            throw new \RuntimeException(
                $method . " is not a valid connection type."
            );
        }
        if (!\in_array($method, PDO::getAvailableDrivers())) {
            throw new \RuntimeException(
                "PHP PDO extension for " .
                    $method .
                    " is not installed/configured."
            );
        }
        if (!\in_array($method, \array_keys($this->connections))) {
            throw new \RuntimeException(
                "There are no " .
                    $method .
                    " connection configurations defined."
            );
        }
        $connection = $this->getNamedConnection($method, $args[0]);
        if (!$connection) {
            throw new \RuntimeException(
                "There is no " .
                    $method .
                    " connection configuration named " .
                    $args[0]
            );
        }
        if ($method === "mysql") {
            return new MYSQLDriver($connection);
        }
        if ($method === "sqlite") {
            return new SQLITEDriver($connection);
        }
        if ($method === "sqlsrv") {
            return new SQLSRVDriver($connection);
        }
    }

    private function getNamedConnection($type, $name)
    {
        return \array_reduce($this->connections[$type], function (
            $state,
            $item
        ) use ($name) {
            if ($item["name"] !== $name) {
                return $state;
            }
            if ($state === null) {
                return $item;
            }
            return $state;
        });
    }
}

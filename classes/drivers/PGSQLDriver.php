<?php

namespace Grav\Plugin\Database\Drivers;

use Grav\Common\Grav;
use Grav\Plugin\Database\PDO;

class PGSQLDriver
{
    protected $pdo;

    public function __construct($connection)
    {
        $host = $connection["host"];
        $port = $connection["port"];
        $dbname = $connection["dbname"];
        $username = $connection["user"];
        $password = $connection["password"];
        $sslmode = \in_array($connection["sslmode"], [
            "disable",
            "allow",
            "prefer",
            "require",
            "verify-ca",
            "verify-full",
        ])
            ? $connection["sslmode"]
            : "prefer";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $dsn = "pgsql:host=${host};port=${port};dbname=${dbname};user=${username};password=${password};sslmode=${sslmode}";
        $this->pdo = Grav::instance()["database"]->connect(
            $dsn,
            $username,
            $password,
            $options
        );
    }

    // a proxy to native PDO methods
    public function __call($method, $args)
    {
        try {
            return \call_user_func_array([$this->pdo, $method], $args);
        } catch (\PDOException $e) {
            // We got an exception == table not found
            throw new \PDOException($e->getMessage(), (int) $e->getCode());
        }
    }
}

<?php

namespace Grav\Plugin\Database\Drivers;

use Grav\Common\Grav;
use Grav\Plugin\Database\PDO;

class SQLSRVDriver
{
    protected $pdo;

    public function __construct($connection)
    {
        $server = $connection["server"];
        $port = $connection["port"];
        $database = $connection["database"];
        $encrypt = $connection["encrypt"] ? "true" : "false";
        $username = $connection["username"];
        $password = $connection["password"];
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $dsn =
            "sqlsrv:Server=" .
            $server .
            "," .
            $port .
            ";Database=" .
            $database .
            ";Encrypt=" .
            $encrypt;
        $this->pdo = Grav::instance()["database"]->connect(
            $dsn,
            $username,
            $password
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

<?php

namespace Grav\Plugin\Database\Drivers;

use Grav\Common\Grav;
use Grav\Plugin\Database\PDO;

class SQLITEDriver
{
    protected $pdo;

    public function __construct($connection)
    {
        $directory = $connection["directory"];
        $filename = $connection["filename"];
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $dsn = "sqlite:" . $directory . "/" . $filename;
        $this->pdo = Grav::instance()["database"]->connect(
            $dsn,
            "",
            "",
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

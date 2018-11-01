<?php

namespace Grav\Plugin\Database;

class Database
{
    private $connections = [];

    public function connect($connect_string)
    {
        if (!array_key_exists($connect_string, $this->connections)) {
            $this->connections[$connect_string] = new PDO($connect_string);
        }
        return $this->connections[$connect_string];
    }
}
<?php

namespace Grav\Plugin\Database;

class Database
{
    private $pdo = [];

    public function connect($connect_string, $user='',$pwd='', $options=[])
    {
        if (!array_key_exists($connect_string, $this->pdo)) {
            $this->pdo[$connect_string] = new PDO($connect_string, $user, $pwd, $options);
        }
        return $this->pdo[$connect_string];
    }
}

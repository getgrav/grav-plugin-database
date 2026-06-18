<?php
namespace Grav\Plugin\Database;

require_once __DIR__ . '/StatementHelpers.php';

class PDO extends \PDO
{
    use StatementHelpers;
}

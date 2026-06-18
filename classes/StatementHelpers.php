<?php
namespace Grav\Plugin\Database;

/**
 * Shared query helpers used by both the native PDO wrapper and the YetiSQL
 * wrapper. Both expose the same `select`/`selectall`/`update`/`delete`/`insert`
 * sugar and a safe `tableExists()` regardless of the underlying engine.
 */
trait StatementHelpers
{
    public function __call($func, $args)
    {
        if (
            !\in_array($func, [
                'select',
                'selectall',
                'update',
                'delete',
                'insert',
            ])
        ) {
            throw new \RuntimeException($func . ' is not a valid statement');
        }

        if (\count($args) === 2) {
            $stmt = parent::prepare($args[0]);
            $stmt->execute($args[1]);
        } elseif ($args) {
            $stmt = parent::query($args[0]);
        }
        if ((int) $stmt->errorCode()) {
            throw new \RuntimeException($stmt->errorInfo()[2]);
        }
        if ($func === 'select') {
            return $stmt->fetch();
        }
        if ($func === 'selectall') {
            return $stmt->fetchAll();
        }
        if ($func === 'insert') {
            return parent::lastInsertId();
        }

        return $stmt->rowCount();
    }

    public function tableExists($table)
    {
        // Look the table up via a parameterized catalog query instead of
        // interpolating the name into "SELECT 1 FROM $table", which was an SQL
        // injection vector if a caller passed untrusted input (GHSA-8jxg-4pw9-xcwf).
        $driver = $this->getAttribute(\PDO::ATTR_DRIVER_NAME);

        try {
            switch ($driver) {
                case 'sqlite':
                case 'yetisql':
                    $sql = "SELECT 1 FROM sqlite_master WHERE type IN ('table', 'view') AND name = ? LIMIT 1";
                    break;
                case 'pgsql':
                    $sql = "SELECT 1 FROM information_schema.tables WHERE table_name = ? LIMIT 1";
                    break;
                case 'sqlsrv':
                    $sql = "SELECT 1 FROM information_schema.tables WHERE table_name = ?";
                    break;
                case 'mysql':
                default:
                    $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1";
                    break;
            }

            $stmt = parent::prepare($sql);
            $stmt->execute([$table]);

            return $stmt->fetchColumn() !== false;
        } catch (\Throwable $e) {
            // Any failure (missing table, parse/connection error) means we
            // cannot confirm the table exists. Caught broadly because engines
            // throw different exception types (YetiSQL's is not a \PDOException).
            return false;
        }
    }
}

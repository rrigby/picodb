<?php

namespace PicoDb\Driver;

use PDO;
use LogicException;
use PDOException;

/**
 * Base Driver class
 *
 * @package PicoDb\Driver
 * @author  Frederic Guillot
 */
abstract class Base
{
    /**
     * List of required settings options
     */
    protected array $requiredAttributes = [];

    /**
     * PDO connection
     */
    private ?PDO $pdo = null;

    /**
     * Create a new PDO connection
     */
    abstract public function createConnection(array $settings): void;

    /**
     * Enable foreign keys
     */
    abstract public function enableForeignKeys(): void;

    /**
     * Disable foreign keys
     */
    abstract public function disableForeignKeys(): void;

    /**
     * Return true if the error code is a duplicate key
     *
     * @param  integer  $code
     */
    abstract public function isDuplicateKeyError($code): bool;

    /**
     * Escape identifier
     *
     * @param  string  $identifier
     */
    abstract public function escape($identifier): string;

    /**
     * Get non standard operator
     *
     * @param  string  $operator
     */
    abstract public function getOperator($operator): string;

    /**
     * Build a JSON field equality condition
     *
     * Returns a SQL string with a single trailing ? for the comparison value.
     * The JSON path is embedded directly as a string literal.
     *
     * @abstract
     * @param  string  $column    Escaped column identifier
     * @param  string  $path      JSONPath expression (e.g. '$.key' or '$.key1.key2')
     * @param  string  $operator  Comparison operator
     */
    abstract public function buildJsonExtractCondition(string $column, string $path, string $operator): string;

    /**
     * Build a JSON array containment condition
     *
     * Checks that all elements of $values exist in the JSON array stored in $column
     * (optionally at $path within the column).
     *
     * Returns [string $sql, array $bindings] — a complete condition with all bindings included.
     *
     * @abstract
     * @param  string      $column  Escaped column identifier
     * @param  string|null $path    JSONPath expression, or null to target the column directly
     * @param  array       $values  The values that must all be present in the JSON array
     * @return array{0: string, 1: array}
     */
    abstract public function buildJsonContainsCondition(string $column, ?string $path, array $values): array;

    /**
     * Get last inserted id
     *
     * @abstract
     */
    abstract public function getLastId(): string|false;

    /**
     * Get current schema version
     */
    abstract public function getSchemaVersion(): int;

    /**
     * Set current schema version
     *
     * @param  integer  $version
     */
    abstract public function setSchemaVersion($version): void;

    /**
     * Constructor
     */
    public function __construct(array $settings)
    {
        foreach ($this->requiredAttributes as $attribute) {
            if (! isset($settings[$attribute])) {
                throw new LogicException('This configuration parameter is missing: "'.$attribute.'"');
            }
        }

        $this->createConnection($settings);
        $this->getConnection()->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Get the PDO connection
     *
     * @throws LogicException
     */
    public function getConnection(): PDO
    {
        if (!$this->pdo instanceof PDO) {
            throw new LogicException('The database connection is not established.');
        }

        return $this->pdo;
    }

    /**
     * Set the PDO connection
     */
    protected function setConnection(PDO $pdo): void
    {
        $this->pdo = $pdo;
    }

    /**
     * Release the PDO connection
     */
    public function closeConnection(): void
    {
        $this->pdo = null;
    }

    /**
     * Get offset limit clause
     *
     * @param int|null $limit
     * @param int|null $offset
     * @param string|null $order
     */
    public function getLimitClause($limit, $offset, $order): string
    {
        $clause = '';

        if (! is_null($limit)) {
            $clause .= ' LIMIT ' . $limit;
        }

        if (! is_null($offset)) {
            $clause .= '  OFFSET ' . $offset;
        }

        return $clause;
    }

    /**
     * Upsert for a key/value variable
     *
     * @param  string  $table
     * @param  string  $keyColumn
     * @param  string  $valueColumn
     */
    public function upsert($table, $keyColumn, $valueColumn, array $dictionary): bool
    {
        try {
            $this->getConnection()->beginTransaction();

            foreach ($dictionary as $key => $value) {

                $rq = $this->getConnection()->prepare('SELECT 1 FROM '.$this->escape($table).' WHERE '.$this->escape($keyColumn).'=?');
                $rq->execute([$key]);

                if ($rq->fetchColumn()) {
                    $rq = $this->getConnection()->prepare('UPDATE '.$this->escape($table).' SET '.$this->escape($valueColumn).'=? WHERE '.$this->escape($keyColumn).'=?');
                    $rq->execute([$value, $key]);
                }
                else {
                    $rq = $this->getConnection()->prepare('INSERT INTO '.$this->escape($table).' ('.$this->escape($keyColumn).', '.$this->escape($valueColumn).') VALUES (?, ?)');
                    $rq->execute([$key, $value]);
                }
            }

            $this->getConnection()->commit();

            return true;
        }
        catch (PDOException) {
            $this->getConnection()->rollBack();
            return false;
        }
    }

    /**
     * Run EXPLAIN command
     *
     * @param  string $sql
     */
    public function explain($sql, array $values): array
    {
        return $this->getConnection()->query('EXPLAIN '.$this->getSqlFromPreparedStatement($sql, $values))->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Replace placeholder with values in prepared statement
     *
     * @param  string $sql
     */
    protected function getSqlFromPreparedStatement($sql, array $values): string
    {
        foreach ($values as $value) {
            $pos = strpos($sql, '?');
            if ($pos === false) {
                break;
            }
            $quoted = is_null($value) ? "''" : $this->getConnection()->quote($value);
            $sql = substr_replace($sql, $quoted, $pos, 1);
        }

        return $sql;
    }

    /**
     * Get database version
     */
    public function getDatabaseVersion(): mixed
    {
        return $this->getConnection()->query('SELECT VERSION()')->fetchColumn();
    }
}

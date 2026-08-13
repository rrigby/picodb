<?php

declare(strict_types=1);

namespace PicoDb\Driver;

use PDO;
use PDOException;

/**
 * Sqlite Driver
 *
 * @package PicoDb\Driver
 * @author  Frederic Guillot
 */
class Sqlite extends Base
{
    /**
     * List of required settings options
     *
     * @var string[]
     */
    protected array $requiredAttributes = ['filename'];

    /**
     * Create a new PDO connection
     */
    public function createConnection(array $settings): void
    {
        $options = [];

        if (! empty($settings['timeout'])) {
            $options[PDO::ATTR_TIMEOUT] = $settings['timeout'];
        }

        $this->setConnection(new PDO('sqlite:'.$settings['filename'], null, null, $options));
        $this->enableForeignKeys();
    }

    /**
     * Enable foreign keys
     */
    public function enableForeignKeys(): void
    {
        $this->getConnection()->exec('PRAGMA foreign_keys = ON');
    }

    /**
     * Disable foreign keys
     */
    public function disableForeignKeys(): void
    {
        $this->getConnection()->exec('PRAGMA foreign_keys = OFF');
    }

    /**
     * Return true if the error code is a duplicate key
     */
    public function isDuplicateKeyError(int $code): bool
    {
        return $code === 23000;
    }

    /**
     * Escape identifier
     */
    public function escape(string $identifier): string
    {
        return '"'.$identifier.'"';
    }

    /**
     * Get non standard operator
     */
    public function getOperator(string $operator): string
    {
        if ($operator === 'LIKE' || $operator === 'ILIKE') {
            return 'LIKE';
        }

        return '';
    }

    public function buildJsonExtractCondition(string $column, string $path, string $operator): string
    {
        return 'JSON_EXTRACT('.$column.', \''.$path.'\') '.$operator.' ?';
    }

    public function buildJsonContainsCondition(string $column, ?string $path, array $values): array
    {
        $count = count($values);
        $placeholders = implode(', ', array_fill(0, $count, '?'));
        $target = $path !== null ? 'JSON_EXTRACT('.$column.', \''.$path.'\')' : $column;
        $sql = '(SELECT COUNT(*) FROM JSON_EACH('.$target.') WHERE value IN ('.$placeholders.')) = '.$count;

        return [$sql, $values];
    }

    /**
     * Get last inserted id
     */
    public function getLastId(): string|false
    {
        return $this->getConnection()->lastInsertId();
    }

    /**
     * Get current schema version
     */
    public function getSchemaVersion(): int
    {
        $rq = $this->getConnection()->prepare('PRAGMA user_version');
        $rq->execute();

        return (int) $rq->fetchColumn();
    }

    /**
     * Set current schema version
     */
    public function setSchemaVersion(int $version): void
    {
        $this->getConnection()->exec('PRAGMA user_version='.$version);
    }

    /**
     * Upsert for a key/value variable
     *
     * @return bool    False on failure
     */
    public function upsert(string $table, string $keyColumn, string $valueColumn, array $dictionary): bool
    {
        try {
            $this->getConnection()->beginTransaction();

            foreach ($dictionary as $key => $value) {

                $sql = sprintf(
                    'INSERT OR REPLACE INTO %s (%s, %s) VALUES (?, ?)',
                    $this->escape($table),
                    $this->escape($keyColumn),
                    $this->escape($valueColumn)
                );

                $rq = $this->getConnection()->prepare($sql);
                $rq->execute([$key, $value]);
            }

            $this->getConnection()->commit();

            return true;
        } catch (PDOException) {
            $this->getConnection()->rollBack();
            return false;
        }
    }

    /**
     * Run EXPLAIN command
     */
    public function explain(string $sql, array $values): array
    {
        return $this->getConnection()->query('EXPLAIN QUERY PLAN '.$this->getSqlFromPreparedStatement($sql, $values))->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get database version
     */
    public function getDatabaseVersion(): mixed
    {
        return $this->getConnection()->query('SELECT sqlite_version()')->fetchColumn();
    }
}

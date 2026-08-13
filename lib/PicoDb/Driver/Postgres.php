<?php

declare(strict_types=1);

namespace PicoDb\Driver;

use PDO;
use PDOException;

/**
 * Postgres Driver
 *
 * @package PicoDb\Driver
 * @author  Frederic Guillot
 */
class Postgres extends Base
{
    /**
     * List of required settings options
     *
     * @var string[]
     */
    protected array $requiredAttributes = [
        'database',
    ];

    /**
     * Table to store the schema version
     */
    private string $schemaTable = 'schema_version';

    /**
     * Create a new PDO connection
     */
    public function createConnection(array $settings): void
    {
        $dsn = 'pgsql:dbname='.$settings['database'];
        $username = null;
        $password = null;
        $options = [];

        if (! empty($settings['username'])) {
            $username = $settings['username'];
        }

        if (! empty($settings['password'])) {
            $password = $settings['password'];
        }

        if (! empty($settings['hostname'])) {
            $dsn .= ';host='.$settings['hostname'];
        }

        if (! empty($settings['port'])) {
            $dsn .= ';port='.$settings['port'];
        }

        if (! empty($settings['timeout'])) {
            $options[PDO::ATTR_TIMEOUT] = $settings['timeout'];
        }

        $this->setConnection(new PDO($dsn, $username, $password, $options));

        if (isset($settings['schema_table'])) {
            $this->schemaTable = $settings['schema_table'];
        }
    }

    /**
     * Enable foreign keys
     */
    public function enableForeignKeys(): void
    {
    }

    /**
     * Disable foreign keys
     */
    public function disableForeignKeys(): void
    {
    }

    /**
     * Return true if the error code is a duplicate key
     */
    public function isDuplicateKeyError(int $code): bool
    {
        return $code === 23505 || $code === 23503;
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
        if ($operator === 'LIKE') {
            return 'LIKE';
        }
        if ($operator === 'ILIKE') {
            return 'ILIKE';
        }

        return '';
    }

    public function buildJsonExtractCondition(string $column, string $path, string $operator): string
    {
        // jsonb_path_query_first() parses the JSONPath natively (PG 12+), so the full
        // grammar is supported (nested keys, array subscripts, wildcards, filters).
        // #>> '{}' unwraps the resulting scalar jsonb to text for comparison.
        return 'jsonb_path_query_first('.$column.", '".$path."') #>> '{}' ".$operator.' ?';
    }

    public function buildJsonContainsCondition(string $column, ?string $path, array $values): array
    {
        if ($path === null) {
            return [$column.' @> ?::jsonb', [json_encode($values)]];
        }

        return ['jsonb_path_query_first('.$column.", '".$path."') @> ?::jsonb", [json_encode($values)]];
    }

    /**
     * Get last inserted id
     */
    public function getLastId(): string|false
    {
        try {
            $rq = $this->getConnection()->prepare('SELECT LASTVAL()');
            $rq->execute();

            return (string) $rq->fetchColumn();
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * Get current schema version
     */
    public function getSchemaVersion(): int
    {
        $this->getConnection()->exec("CREATE TABLE IF NOT EXISTS ".$this->schemaTable." (version INTEGER DEFAULT 0)");

        $rq = $this->getConnection()->prepare('SELECT "version" FROM "'.$this->schemaTable.'"');
        $rq->execute();
        $result = $rq->fetchColumn();

        if ($result !== false) {
            return (int) $result;
        }
        $this->getConnection()->exec('INSERT INTO '.$this->schemaTable.' VALUES(0)');

        return 0;
    }

    /**
     * Set current schema version
     */
    public function setSchemaVersion(int $version): void
    {
        $rq = $this->getConnection()->prepare('UPDATE '.$this->schemaTable.' SET version=?');
        $rq->execute([$version]);
    }

    /**
     * Run EXPLAIN command
     */
    public function explain(string $sql, array $values): array
    {
        return $this->getConnection()->query('EXPLAIN (FORMAT YAML) '.$this->getSqlFromPreparedStatement($sql, $values))->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get database version
     */
    public function getDatabaseVersion(): mixed
    {
        return $this->getConnection()->query('SHOW server_version')->fetchColumn();
    }
}

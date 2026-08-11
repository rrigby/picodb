<?php

namespace PicoDb\Driver;

use PDO;
use PDOException;

/**
 * Mysql Driver
 *
 * @package PicoDb\Driver
 * @author  Frederic Guillot
 */
class Mysql extends Base
{
    /**
     * List of required settings options
     *
     * @var string[]
     */
    protected array $requiredAttributes = [
        'hostname',
        'username',
        'password',
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
        $this->setConnection(new PDO(
            $this->buildDsn($settings),
            $settings['username'],
            $settings['password'],
            $this->buildOptions($settings)
        ));

        if (isset($settings['schema_table'])) {
            $this->schemaTable = $settings['schema_table'];
        }
    }

    /**
     * Build connection DSN
     *
     * @param array<string, mixed> $settings
     */
    protected function buildDsn(array $settings): string
    {
        $dsn = 'mysql:host='.$settings['hostname'].';dbname='.$settings['database'];

        if (! empty($settings['port'])) {
            $dsn .= ';port='.$settings['port'];
        }

        return $dsn;
    }

    /**
     * Build connection options
     *
     * @return array<int, mixed>
     * @param array<string, mixed> $settings
     */
    protected function buildOptions(array $settings): array
    {
        $charset = empty($settings['charset']) ? 'utf8' : $settings['charset'];
        $options = [
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET sql_mode = STRICT_ALL_TABLES, NAMES ' . $charset,
        ];

        if (! empty($settings['ssl_key'])) {
            $options[PDO::MYSQL_ATTR_SSL_KEY] = $settings['ssl_key'];
        }

        if (! empty($settings['ssl_cert'])) {
            $options[PDO::MYSQL_ATTR_SSL_CERT] = $settings['ssl_cert'];
        }

        if (! empty($settings['ssl_ca'])) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $settings['ssl_ca'];
        }

        if (! empty($settings['persistent'])) {
            $options[PDO::ATTR_PERSISTENT] = $settings['persistent'];
        }

        if (! empty($settings['timeout'])) {
            $options[PDO::ATTR_TIMEOUT] = $settings['timeout'];
        }

        if (isset($settings['verify_server_cert'])) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $settings['verify_server_cert'];
        }

        if (! empty($settings['case'])) {
            $options[PDO::ATTR_CASE] = $settings['case'];
        }

        return $options;
    }

    /**
     * Enable foreign keys
     */
    public function enableForeignKeys(): void
    {
        $this->getConnection()->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Disable foreign keys
     */
    public function disableForeignKeys(): void
    {
        $this->getConnection()->exec('SET FOREIGN_KEY_CHECKS=0');
    }

    /**
     * Return true if the error code is a duplicate key
     *
     * @param  integer  $code
     */
    public function isDuplicateKeyError($code): bool
    {
        return $code == 23000;
    }

    /**
     * Escape identifier
     *
     * @param  string  $identifier
     */
    public function escape($identifier): string
    {
        return '`'.$identifier.'`';
    }

    /**
     * Get non standard operator
     *
     * @param  string  $operator
     */
    public function getOperator($operator): string
    {
        if ($operator === 'LIKE') {
            return 'LIKE BINARY';
        }
        if ($operator === 'ILIKE') {
            return 'LIKE';
        }

        return '';
    }

    public function buildJsonExtractCondition(string $column, string $path, string $operator): string
    {
        return 'JSON_UNQUOTE(JSON_EXTRACT('.$column.', \''.$path.'\')) '.$operator.' ?';
    }

    public function buildJsonContainsCondition(string $column, ?string $path, array $values): array
    {
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        if ($path === null) {
            return ['JSON_CONTAINS('.$column.', JSON_ARRAY('.$placeholders.'))', $values];
        }

        return ['JSON_CONTAINS(JSON_EXTRACT('.$column.', \''.$path.'\'), JSON_ARRAY('.$placeholders.'))', $values];
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
        $this->getConnection()->exec("CREATE TABLE IF NOT EXISTS `".$this->schemaTable."` (`version` INT DEFAULT '0') ENGINE=InnoDB CHARSET=utf8");

        $rq = $this->getConnection()->prepare('SELECT `version` FROM `'.$this->schemaTable.'`');
        $rq->execute();
        $result = $rq->fetchColumn();

        if ($result !== false) {
            return (int) $result;
        }
        $this->getConnection()->exec('INSERT INTO `'.$this->schemaTable.'` VALUES(0)');

        return 0;
    }

    /**
     * Set current schema version
     *
     * @param  integer  $version
     */
    public function setSchemaVersion($version): void
    {
        $rq = $this->getConnection()->prepare('UPDATE `'.$this->schemaTable.'` SET `version`=?');
        $rq->execute([$version]);
    }

    /**
     * Upsert for a key/value variable
     *
     * @param  string  $table
     * @param  string  $keyColumn
     * @param  string  $valueColumn
     * @return bool    False on failure
     */
    public function upsert($table, $keyColumn, $valueColumn, array $dictionary): bool
    {
        try {

            $sql = sprintf(
                'REPLACE INTO %s (%s, %s) VALUES %s',
                $this->escape($table),
                $this->escape($keyColumn),
                $this->escape($valueColumn),
                implode(', ', array_fill(0, count($dictionary), '(?, ?)'))
            );

            $values = [];

            foreach ($dictionary as $key => $value) {
                $values[] = $key;
                $values[] = $value;
            }

            $rq = $this->getConnection()->prepare($sql);
            $rq->execute($values);

            return true;
        }
        catch (PDOException) {
            return false;
        }
    }
}

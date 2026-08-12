<?php

declare(strict_types=1);

namespace PicoDb;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Statement Handler
 *
 * @package PicoDb
 * @author  Frederic Guillot
 */
class StatementHandler
{
    /**
     * Flag to calculate query time
     */
    protected bool $stopwatch = false;

    protected float $startTime = 0;

    /**
     * Execution time of all queries
     */
    protected float $executionTime = 0;

    /**
     * Flag to log generated SQL queries
     */
    protected bool $logQueries = false;

    /**
     * Flag to combine values in the logged SQL queries.
     */
    protected bool $logQueryValues = false;

    /**
     * Run explain command on each query
     */
    protected bool $explain = false;

    /**
     * Number of SQL queries executed
     */
    protected int $nbQueries = 0;

    protected string $sql = '';

    /**
     * Positional SQL parameters
     */
    protected array $positionalParams = [];

    /**
     * Named SQL parameters
     */
    protected array $namedParams = [];

    /**
     * Flag to use named params
     */
    protected bool $useNamedParams = false;

    /**
     * LOB params
     */
    protected array $lobParams = [];

    /**
     * Constructor
     */
    public function __construct(
        /**
         * Database instance
         */
        protected Database $db
    )
    {
    }

    /**
     * Enable query logging
     */
    public function withLogging(bool $includeValues = false): static
    {
        $this->logQueryValues = $includeValues;
        $this->logQueries = true;
        return $this;
    }

    /**
     * Record query execution time
     */
    public function withStopWatch(): static
    {
        $this->stopwatch = true;
        return $this;
    }

    /**
     * Execute explain command on query
     */
    public function withExplain(): static
    {
        $this->explain = true;
        return $this;
    }

    /**
     * Set SQL query
     */
    public function withSql(string $sql): static
    {
        $this->sql = $sql;
        return $this;
    }

    /**
     * Set positional parameters
     */
    public function withPositionalParams(array $params): static
    {
        $this->positionalParams = $params;
        return $this;
    }

    /**
     * Set named parameters
     */
    public function withNamedParams(array $params): static
    {
        $this->namedParams = $params;
        $this->useNamedParams = true;
        return $this;
    }

    /**
     * Bind large object parameter
     *
     * @param $name
     * @param $fp
     */
    public function withLobParam($name, &$fp): static
    {
        $this->lobParams[$name] =& $fp;
        return $this;
    }

    /**
     * Get number of queries executed
     */
    public function getNbQueries(): int
    {
        return $this->nbQueries;
    }

    /**
     * Execute a prepared statement
     *
     * @throws SQLException
     */
    public function execute(): PDOStatement
    {
        try {
            $this->beforeExecute();

            $pdoStatement = $this->db->getConnection()->prepare($this->sql);

            // Unreachable at runtime (ERRMODE_EXCEPTION makes prepare() throw); kept to satisfy the type checker.
            if ($pdoStatement === false) {
                throw new SQLException('Failed to prepare SQL statement');
            }

            $this->bindParams($pdoStatement);
            $pdoStatement->execute();

            $this->afterExecute();
            return $pdoStatement;
        } catch (PDOException $e) {
            $this->handleSqlError($e);
        }
    }

    /**
     * Bind parameters to PDOStatement
     */
    protected function bindParams(PDOStatement $pdoStatement): void
    {
        $i = 1;

        foreach ($this->lobParams as $name => $variable) {
            if (! $this->useNamedParams) {
                $parameter = $i;
                $i++;
            } else {
                $parameter = $name;
            }

            $pdoStatement->bindParam($parameter, $variable, PDO::PARAM_LOB);
        }

        foreach ($this->positionalParams as $value) {
            $pdoStatement->bindValue($i, $value, PDO::PARAM_STR);
            $i++;
        }

        foreach ($this->namedParams as $name => $value) {
            $pdoStatement->bindValue($name, $value, PDO::PARAM_STR);
        }
    }

    /**
     * Method executed before query execution
     */
    protected function beforeExecute(): void
    {
        if ($this->logQueries) {
            $sql = $this->sql;
            if ($this->logQueryValues) {
                $params = $this->lobParams ?: $this->positionalParams ?: $this->namedParams;

                if ($this->useNamedParams) {
                    $sql = preg_replace_callback('/:([a-zA-Z0-9_]+)/', function (array $matches) use ($params): string {
                        $paramName = $matches[1];
                        $replacement = $params[$paramName] ?? $matches[0];
                        return "'$replacement'";
                    }, $sql);
                } else {
                    $i = 0;
                    $sql = preg_replace_callback('/\?/', function($matches) use ($params, &$i): string {
                        $replacement = $params[$i] ?? '';
                        $i++;
                        return "'$replacement'";
                    }, $sql);
                }
            }
            $this->db->setLogMessage($sql);
        }

        if ($this->stopwatch) {
            $this->startTime = microtime(true);
        }
    }

    /**
     * Method executed after query execution
     */
    protected function afterExecute(): void
    {
        if ($this->stopwatch) {
            $duration = microtime(true) - $this->startTime;
            $this->executionTime += $duration;
            $this->db->setLogMessage('query_duration='.$duration);
            $this->db->setLogMessage('total_execution_time='.$this->executionTime);
        }

        if ($this->explain) {
            $this->db->setLogMessages($this->db->getDriver()->explain($this->sql, $this->positionalParams));
        }

        $this->nbQueries++;
        $this->cleanup();
    }

    /**
     * Reset internal properties after execution
     * The same object instance is used
     */
    protected function cleanup(): void
    {
        $this->sql = '';
        $this->useNamedParams = false;
        $this->positionalParams = [];
        $this->namedParams = [];
        $this->lobParams = [];
    }

    /**
     * Handle PDOException
     *
     * @return never
     * @throws SQLException
     */
    public function handleSqlError(PDOException $e): void
    {
        $this->cleanup();
        $this->db->cancelTransaction();
        $this->db->setLogMessage($e->getMessage());

        throw new SQLException('SQL Error: '.$e->getMessage());
    }
}

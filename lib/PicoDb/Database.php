<?php

declare(strict_types=1);

namespace PicoDb;

use PDO;
use PDOStatement;
use Closure;
use PDOException;
use LogicException;
use PicoDb\Driver\Base;

/**
 * Database
 *
 * @package PicoDb
 * @author  Frederic Guillot
 */
class Database
{
    /**
     * Database instances
     *
     * @static
     */
    private static array $instances = [];

    /**
     * Statement object
     */
    protected StatementHandler $statementHandler;

    /**
     * Queries logs
     *
     * @var string[]|mixed[]
     */
    private array $logs = [];

    /**
     * Driver instance
     */
    private Base $driver;

    /**
     * Initialize the driver
     */
    public function __construct(array $settings = [])
    {
        $this->driver = DriverFactory::getDriver($settings);
        $this->statementHandler = new StatementHandler($this);
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->closeConnection();
    }


    /**
     * Register a new database instance
     *
     * @static
     * @param  string    $name        Instance name
     * @param  Closure   $callback    Callback
     */
    public static function setInstance($name, Closure $callback): void
    {
        self::$instances[$name] = $callback;
    }

    /**
     * Get a database instance
     *
     * @param  string    $name   Instance name
     */
    public static function getInstance($name): self
    {
        if (! isset(self::$instances[$name])) {
            throw new LogicException('No database instance created with that name');
        }

        if (is_callable(self::$instances[$name])) {
            self::$instances[$name] = call_user_func(self::$instances[$name]);
        }

        return self::$instances[$name];
    }

    /**
     * Add a log message
     *
     * @param  mixed $message
     */
    public function setLogMessage($message): static
    {
        $this->logs[] = is_array($message) ? var_export($message, true) : $message;
        return $this;
    }

    /**
     * Add many log messages
     */
    public function setLogMessages(array $messages): static
    {
        foreach ($messages as $message) {
            $this->setLogMessage($message);
        }

        return $this;
    }

    /**
     * Get all queries logs
     */
    public function getLogMessages(): array
    {
        return $this->logs;
    }

    /**
     * Get the PDO connection
     */
    public function getConnection(): PDO
    {
        return $this->driver->getConnection();
    }

    /**
     * Get the Driver instance
     */
    public function getDriver(): Base
    {
        return $this->driver;
    }

    /**
     * Set the Driver instance
     */
    public function setDriver(Base $driver): void
    {
        $this->driver = $driver;
    }

    /**
     * Get the last inserted id
     */
    public function getLastId(): int
    {
        return (int) $this->driver->getLastId();
    }

    /**
     * Get statement object
     */
    public function getStatementHandler(): StatementHandler
    {
        return $this->statementHandler;
    }

    /**
     * Release the PDO connection
     */
    public function closeConnection(): void
    {
        $this->driver->closeConnection();
    }

    /**
     * Escape an identifier (column, table name...)
     *
     * @param  string    $value    Value
     * @param  string    $table    Table name
     */
    public function escapeIdentifier($value, $table = ''): string
    {
        // Do not escape custom query
        if (str_contains($value, '.') || str_contains($value, ' ')) {
            return $value;
        }

        if (! empty($table)) {
            return $this->driver->escape($table).'.'.$this->driver->escape($value);
        }

        return $this->driver->escape($value);
    }

    /**
     * Escape an identifier list
     *
     * @param  array     $identifiers  List of identifiers
     * @param  string    $table        Table name
     * @return string[]
     */
    public function escapeIdentifierList(array $identifiers, $table = ''): array
    {
        foreach ($identifiers as $key => $value) {
            $identifiers[$key] = $this->escapeIdentifier($value, $table);
        }

        return $identifiers;
    }

    /**
     * Execute a prepared statement
     *
     * @param  string    $sql      SQL query
     * @param  array     $values   Values
     * @throws SQLException
     */
    public function execute(string $sql, array $values = []): PDOStatement
    {
        return $this->statementHandler
            ->withSql($sql)
            ->withPositionalParams($values)
            ->execute();
    }

    /**
     * Run a transaction
     *
     * @param  Closure    $callback     Callback
     */
    public function transaction(Closure $callback): mixed
    {
        try {

            $this->startTransaction();
            $result = $callback($this);
            $this->closeTransaction();

            return $result ?? true;
        } catch (PDOException $e) {
            $this->statementHandler->handleSqlError($e);
        }
    }

    /**
     * Checks if inside a transaction
     */
    public function inTransaction(): bool
    {
        return $this->getConnection()->inTransaction();
    }

    /**
     * Begin a transaction
     */
    public function startTransaction(): bool
    {
        if (! $this->inTransaction()) {
            return $this->getConnection()->beginTransaction();
        }

        return false;
    }

    /**
     * Commit a transaction
     */
    public function closeTransaction(): bool
    {
        if ($this->inTransaction()) {
            return $this->getConnection()->commit();
        }

        return false;
    }

    /**
     * Rollback a transaction
     */
    public function cancelTransaction(): bool
    {
        if ($this->inTransaction()) {
            return $this->getConnection()->rollBack();
        }

        return false;
    }

    /**
     * Get a table object
     *
     * @param  string $table
     */
    public function table($table): Table
    {
        return new Table($this, $table);
    }

    /**
     * Get a hashtable object
     *
     * @param  string $table
     */
    public function hashtable($table): Hashtable
    {
        return new Hashtable($this, $table);
    }

    /**
     * Get a LOB object
     *
     * @param  string $table
     */
    public function largeObject($table): LargeObject
    {
        return new LargeObject($this, $table);
    }

    /**
     * Get a schema object
     *
     * @param  string $namespace
     */
    public function schema($namespace = null): Schema
    {
        $schema = new Schema($this);

        if ($namespace !== null) {
            $schema->setNamespace($namespace);
        }

        return $schema;
    }
}

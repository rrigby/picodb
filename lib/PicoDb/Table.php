<?php

declare(strict_types=1);

namespace PicoDb;

use Closure;
use PDO;
use PicoDb\Builder\AggregatedConditionBuilder;
use PicoDb\Builder\ConditionBuilder;
use PicoDb\Builder\InsertBuilder;
use PicoDb\Builder\UpdateBuilder;
use PicoDb\Driver\Mssql;

/**
 * Table
 *
 * @package PicoDb
 * @author  Frederic Guillot
 *
 * @method   $this   addCondition($sql)
 * @method   $this   beginNot()
 * @method   $this   closeNot()
 * @method   $this   beginAnd()
 * @method   $this   closeAnd()
 * @method   $this   beginOr()
 * @method   $this   closeOr()
 * @method   $this   beginXor()
 * @method   $this   closeXor()
 * @method   $this   eq($column, $value)
 * @method   $this   neq($column, $value)
 * @method   $this   in($column, array $values)
 * @method   $this   inSubquery($column, Table $subquery)
 * @method   $this   notIn($column, array $values)
 * @method   $this   notInSubquery($column, Table $subquery)
 * @method   $this   like($column, $value)
 * @method   $this   ilike($column, $value)
 * @method   $this   notLike($column, $value)
 * @method   $this   gt($column, $value)
 * @method   $this   gtSubquery($column, Table $subquery)
 * @method   $this   lt($column, $value)
 * @method   $this   ltSubquery($column, Table $subquery)
 * @method   $this   gte($column, $value)
 * @method   $this   gteSubquery($column, Table $subquery)
 * @method   $this   lte($column, $value)
 * @method   $this   lteSubquery($column, Table $subquery)
 * @method   $this   between($column, $lowValue, $highValue)
 * @method   $this   notBetween($column, $lowValue, $highValue)
 * @method   $this   isNull($column)
 * @method   $this   notNull($column)
 * @method   $this   jsonEq(string $column, string $path, mixed $value)
 * @method   $this   jsonNeq(string $column, string $path, mixed $value)
 * @method   $this   jsonContains(string $column, array $values, ?string $path = null)
 * @method   $this   jsonNotContains(string $column, array $values, ?string $path = null)
 */
class Table
{
    /**
     * Sorting direction
     *
     * @var string
     */
    public const SORT_ASC = 'ASC';
    public const SORT_DESC = 'DESC';

    protected ConditionBuilder $conditionBuilder;

    protected AggregatedConditionBuilder $aggregatedConditionBuilder;

    /**
     * Columns list for SELECT query
     *
     * @var string[]
     */
    private array $columns = [];

    private array $sumColumns = [];

    /**
     * SQL limit
     */
    private ?int $sqlLimit = null;

    /**
     * SQL offset
     */
    private ?int $sqlOffset = null;

    /**
     * SQL order
     */
    private string $sqlOrder = '';

    private string $sqlSelect = '';

    /**
     * SQL joins
     *
     * @var string[]
     */
    private array $joins = [];

    /**
     * @var mixed[]
     */
    private array $joinValues = [];

    /**
     * Use DISTINCT or not?
     */
    private bool $distinct = false;

    /**
     * Group by those columns
     */
    private array $groupBy = [];

    /**
     * Flag to use the AggregateConditionBuilder (HAVING) or ConditionBuilder (WHERE)
     */
    private string $conditionalBuilder = 'WHERE';

    /**
     * Callback for result filtering
     */
    private ?Closure $callback = null;

    /**
     * Constructor
     */
    public function __construct(protected Database $db, protected string $name)
    {
        $this->conditionBuilder = new ConditionBuilder($this->db);
        $this->aggregatedConditionBuilder = new AggregatedConditionBuilder($this->db);
    }

    /**
     * Return the table name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Return ConditionBuilder object
     */
    public function getConditionBuilder(): ConditionBuilder
    {
        return $this->conditionBuilder;
    }

    /**
     * Return AggregatedConditionBuilder object
     */
    public function getAggregatedConditionBuilder(): AggregatedConditionBuilder
    {
        return $this->aggregatedConditionBuilder;
    }

    /**
     * Insert or update
     */
    public function save(array $data): bool
    {
        return $this->conditionBuilder->hasCondition() ? $this->update($data) : $this->insert($data);
    }

    /**
     * Update
     */
    public function update(array $data = []): bool
    {
        $values = array_merge(array_values($data), array_values($this->sumColumns), $this->conditionBuilder->getValues());
        $sql = UpdateBuilder::getInstance($this->db, $this->conditionBuilder)
            ->withTable($this->name)
            ->withColumns(array_keys($data))
            ->withSumColumns(array_keys($this->sumColumns))
            ->build();

        $this->db->execute($sql, $values);
        return true;
    }

    /**
     * Insert
     */
    public function insert(array $data): bool
    {
        $this->db->getStatementHandler()
            ->withSql(
                InsertBuilder::getInstance($this->db, $this->conditionBuilder)
                ->withTable($this->name)
                ->withColumns(array_keys($data))
                ->build()
            )
            ->withNamedParams($data)
            ->execute();
        return true;
    }

    /**
     * Insert a new row and return the ID of the primary key
     */
    public function persist(array $data): int|false
    {
        if ($this->insert($data)) {
            return $this->db->getLastId();
        }

        return false;
    }

    /**
     * Remove
     */
    public function remove(): bool
    {
        $sql = sprintf(
            'DELETE FROM %s %s',
            $this->db->escapeIdentifier($this->name),
            $this->conditionBuilder->build()
        );

        $result = $this->db->execute($sql, $this->conditionBuilder->getValues());
        return $result->rowCount() > 0;
    }

    /**
     * Fetch all rows
     */
    public function findAll(): array
    {
        $rq = $this->db->execute($this->buildSelectQuery(), $this->getValues());
        $results = $rq->fetchAll(PDO::FETCH_ASSOC);

        if (is_callable($this->callback) && ! empty($results)) {
            return call_user_func($this->callback, $results);
        }

        return $results;
    }

    /**
     * Find all with a single column
     */
    public function findAllByColumn(string $column): array
    {
        $this->columns = [$column];
        $rq = $this->db->execute($this->buildSelectQuery(), $this->getValues());

        return $rq->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    /**
     * Fetch one row
     */
    public function findOne(): ?array
    {
        $this->limit(1);
        $result = $this->findAll();

        return $result[0] ?? null;
    }

    /**
     * Fetch one column, first row
     */
    public function findOneColumn(string $column): string|int|null|false
    {
        $this->limit(1);
        $this->columns = [$column];

        return $this->db->execute($this->buildSelectQuery(), $this->getValues())->fetchColumn();
    }

    /**
     * Build a subquery with an alias
     */
    public function subquery(string $sql, string $alias): static
    {
        $this->columns[] = '('.$sql.') AS '.$this->db->escapeIdentifier($alias);
        return $this;
    }

    /**
     * Exists
     */
    public function exists(): bool
    {
        $sql = sprintf(
            'SELECT 1 FROM %s %s %s %s %s %s %s',
            $this->db->escapeIdentifier($this->name),
            implode(' ', $this->joins),
            $this->conditionBuilder->build(),
            $this->groupBy === [] ? '' : 'GROUP BY '.implode(', ', $this->groupBy),
            $this->aggregatedConditionBuilder->build(),
            $this->sqlOrder,
            $this->db->getDriver()->getLimitClause(
                $this->sqlLimit,
                $this->sqlOffset,
                $this->sqlOrder
            )
        );

        $rq = $this->db->execute($sql, $this->getValues());
        $result = $rq->fetchColumn();

        return (bool) $result;
    }

    /**
     * Count
     */
    public function count(string $column = '*'): int
    {
        if ($column !== '*') {
            $column = ($this->distinct ? 'DISTINCT ' : '') . $this->db->escapeIdentifier($column);
        }

        $sql = sprintf(
            'SELECT COUNT(' . $column . ') FROM %s %s %s %s %s %s %s',
            $this->db->escapeIdentifier($this->name),
            implode(' ', $this->joins),
            $this->conditionBuilder->build(),
            $this->groupBy === [] ? '' : 'GROUP BY '.implode(', ', $this->groupBy),
            $this->aggregatedConditionBuilder->build(),
            $this->sqlOrder,
            $this->db->getDriver()->getLimitClause(
                $this->sqlLimit,
                $this->sqlOffset,
                $this->sqlOrder
            )
        );

        $rq = $this->db->execute($sql, $this->getValues());
        $result = $rq->fetchColumn();

        return $result ? (int) $result : 0;
    }

    /**
     * Sum
     *
     * @return float
     */
    public function sum(string $column): float|int
    {
        $sql = sprintf(
            'SELECT SUM(%s) FROM %s %s %s %s %s %s %s',
            $this->db->escapeIdentifier($column),
            $this->db->escapeIdentifier($this->name),
            implode(' ', $this->joins),
            $this->conditionBuilder->build(),
            $this->groupBy === [] ? '' : 'GROUP BY '.implode(', ', $this->groupBy),
            $this->aggregatedConditionBuilder->build(),
            $this->sqlOrder,
            $this->db->getDriver()->getLimitClause(
                $this->sqlLimit,
                $this->sqlOffset,
                $this->sqlOrder
            )
        );

        $rq = $this->db->execute($sql, $this->getValues());
        $result = $rq->fetchColumn();

        return $result ? (float) $result : 0;
    }

    /**
     * Increment column value
     */
    public function increment(string $column, int $value): bool
    {
        $sql = sprintf(
            'UPDATE %s SET %s=%s+%d '.$this->conditionBuilder->build(),
            $this->db->escapeIdentifier($this->name),
            $this->db->escapeIdentifier($column),
            $this->db->escapeIdentifier($column),
            $value
        );

        $this->db->execute($sql, $this->conditionBuilder->getValues());
        return true;
    }

    /**
     * Decrement column value
     */
    public function decrement(string $column, int $value): bool
    {
        $sql = sprintf(
            'UPDATE %s SET %s=%s-%d '.$this->conditionBuilder->build(),
            $this->db->escapeIdentifier($this->name),
            $this->db->escapeIdentifier($column),
            $this->db->escapeIdentifier($column),
            $value
        );

        $this->db->execute($sql, $this->conditionBuilder->getValues());
        return true;
    }

    /**
     * Left join
     */
    public function join(string $table, string $foreign_column, string $local_column, string $local_table = '', string $alias = ''): static
    {
        $this->joins[] = sprintf(
            'LEFT JOIN %s ON %s=%s',
            $this->db->escapeIdentifier($table),
            $this->db->escapeIdentifier($alias ?: $table).'.'.$this->db->escapeIdentifier($foreign_column),
            $this->db->escapeIdentifier($local_table ?: $this->name).'.'.$this->db->escapeIdentifier($local_column)
        );

        return $this;
    }

    /**
     * Left join
     */
    public function left(string $table1, string $alias1, string $column1, string $table2, string $column2, array $conditions = []): static
    {
        $where = '';
        foreach ($conditions as $column => $value) {
            if (is_array($value)) {
                $where .= ' AND ' . $this->db->escapeIdentifier($alias1) . '.' . $this->db->escapeIdentifier($column) . ' IN (' . implode(',', array_fill(0, count($value), '?')) . ')';
                $this->joinValues = array_merge($this->joinValues, $value);
            } elseif (is_null($value)) {
                $where .= ' AND ' . $this->db->escapeIdentifier($alias1) . '.' . $this->db->escapeIdentifier($column) . ' IS NULL';
            } else {
                $where .= ' AND ' . $this->db->escapeIdentifier($alias1) . '.' . $this->db->escapeIdentifier($column) . ' = ?';
                $this->joinValues[] = $value;
            }
        }

        $this->joins[] = sprintf(
            'LEFT JOIN %s AS %s ON %s=%s%s',
            $this->db->escapeIdentifier($table1),
            $this->db->escapeIdentifier($alias1),
            $this->db->escapeIdentifier($alias1).'.'.$this->db->escapeIdentifier($column1),
            $this->db->escapeIdentifier($table2).'.'.$this->db->escapeIdentifier($column2),
            $where
        );

        return $this;
    }

    /**
     * Inner join
     */
    public function inner(string $table1, string $alias1, string $column1, string $table2, string $column2, array $conditions = []): static
    {
        $where = '';
        foreach ($conditions as $column => $value) {
            if (is_array($value)) {
                $where .= ' AND ' . $this->db->escapeIdentifier($alias1) . '.' . $this->db->escapeIdentifier($column) . ' IN (' . implode(',', array_fill(0, count($value), '?')) . ')';
                $this->joinValues = array_merge($this->joinValues, $value);
            } elseif (is_null($value)) {
                $where .= ' AND ' . $this->db->escapeIdentifier($alias1) . '.' . $this->db->escapeIdentifier($column) . ' IS NULL';
            } else {
                $where .= ' AND ' . $this->db->escapeIdentifier($alias1) . '.' . $this->db->escapeIdentifier($column) . ' = ?';
                $this->joinValues[] = $value;
            }
        }

        $this->joins[] = sprintf(
            'JOIN %s AS %s ON %s=%s%s',
            $this->db->escapeIdentifier($table1),
            $this->db->escapeIdentifier($alias1),
            $this->db->escapeIdentifier($alias1).'.'.$this->db->escapeIdentifier($column1),
            $this->db->escapeIdentifier($table2).'.'.$this->db->escapeIdentifier($column2),
            $where
        );

        return $this;
    }

    /**
     * Join your table onto a subquery.
     */
    public function joinSubquery(Table $subQuery, string $alias, string $foreign_column, string $local_column, string $local_table = ''): Table
    {
        $this->joins[] = sprintf(
            'LEFT JOIN (%s) AS %s ON %s=%s',
            $subQuery->buildSelectQuery(),
            $this->db->escapeIdentifier($alias),
            $this->db->escapeIdentifier($alias).'.'.$this->db->escapeIdentifier($foreign_column),
            $this->db->escapeIdentifier($local_table ?: $this->name).'.'.$this->db->escapeIdentifier($local_column)
        );

        $this->joinValues = array_merge(
            $this->joinValues,
            $subQuery->getValues()
        );

        return $this;
    }

    /**
     * Inner Join your table onto a subquery.
     */
    public function innerJoinSubquery(Table $subQuery, string $alias, string $foreign_column, string $local_column, string $local_table = ''): Table
    {
        $this->joins[] = sprintf(
            'INNER JOIN (%s) AS %s ON %s=%s',
            $subQuery->buildSelectQuery(),
            $this->db->escapeIdentifier($alias),
            $this->db->escapeIdentifier($alias).'.'.$this->db->escapeIdentifier($foreign_column),
            $this->db->escapeIdentifier($local_table ?: $this->name).'.'.$this->db->escapeIdentifier($local_column)
        );

        $this->joinValues = array_merge(
            $this->joinValues,
            $subQuery->getValues()
        );

        return $this;
    }

    /**
     * Order by
     */
    public function orderBy(string $column, string $order = self::SORT_ASC): static
    {
        $order = strtoupper($order);
        $order = $order === self::SORT_ASC || $order === self::SORT_DESC ? $order : self::SORT_ASC;

        if ($this->sqlOrder === '') {
            $this->sqlOrder = ' ORDER BY '.$this->db->escapeIdentifier($column).' '.$order;
        } else {
            $this->sqlOrder .= ', '.$this->db->escapeIdentifier($column).' '.$order;
        }

        return $this;
    }

    /**
     * Ascending sort
     */
    public function asc(string $column): static
    {
        $this->orderBy($column, self::SORT_ASC);
        return $this;
    }

    /**
     * Descending sort
     */
    public function desc(string $column): static
    {
        $this->orderBy($column, self::SORT_DESC);
        return $this;
    }

    /**
     * Limit
     */
    public function limit($value): static
    {
        if (! is_null($value)) {
            $this->sqlLimit = (int) $value;
        }

        return $this;
    }

    /**
     * Offset
     */
    public function offset($value): static
    {
        if (! is_null($value)) {
            $this->sqlOffset = (int) $value;
        }

        return $this;
    }

    /**
     * Group By
     *
     * @param string ...$columns
     */
    public function groupBy(...$columns): static
    {
        $this->groupBy = $columns;
        return $this;
    }

    /**
     * Custom select
     */
    public function select(string $select): static
    {
        $this->sqlSelect = $select;
        return $this;
    }

    /**
     * Define the columns for the select
     */
    public function columns(): static
    {
        $this->columns = func_get_args();
        return $this;
    }

    /**
     * Sum column
     */
    public function sumColumn(string $column, mixed $value): static
    {
        $this->sumColumns[$column] = $value;
        return $this;
    }

    /**
     * Distinct
     */
    public function distinct(): static
    {
        $this->columns = func_get_args();
        $this->distinct = true;
        return $this;
    }

    /**
     * Add callback to alter the resultset
     */
    public function callback(callable $callback): static
    {
        $this->callback = Closure::fromCallable($callback);
        return $this;
    }

    /**
     * Build a select query
     */
    public function buildSelectQuery(): string
    {
        if ($this->sqlSelect === '' || $this->sqlSelect === '0') {
            $this->columns = $this->db->escapeIdentifierList($this->columns);
            $this->sqlSelect = ($this->distinct ? 'DISTINCT ' : '').($this->columns === [] ? '*' : implode(', ', $this->columns));
        }

        $groupBy = $this->db->escapeIdentifierList($this->groupBy);
        $selectLimit = '';

        // MSSQL uses SELECT TOP n [columns] instead of LIMIT when OFFSET is not specified
        if (
            $this->db->getDriver() instanceof Mssql &&
            ! is_null($this->sqlLimit) &&
            is_null($this->sqlOffset)
        ) {
            $selectLimit = 'TOP '.$this->sqlLimit.' ';
        }

        return trim(sprintf(
            'SELECT %s%s FROM %s %s %s %s %s %s %s',
            $selectLimit,
            $this->sqlSelect,
            $this->db->escapeIdentifier($this->name),
            implode(' ', $this->joins),
            $this->conditionBuilder->build(),
            $groupBy === [] ? '' : 'GROUP BY '.implode(', ', $groupBy),
            $this->aggregatedConditionBuilder->build(),
            $this->sqlOrder,
            $this->db->getDriver()->getLimitClause(
                $this->sqlLimit,
                $this->sqlOffset,
                $this->sqlOrder
            )
        ));
    }

    /**
     * Sets the conditionalBuilder flag to use AggregateConditionBuilder (HAVING)
     */
    public function having(): static
    {
        $this->conditionalBuilder = 'HAVING';
        return $this;
    }

    /**
     * Sets the conditionalBuilder flag to use ConditionBuilder (WHERE)
     */
    public function where(): static
    {
        $this->conditionalBuilder = 'WHERE';
        return $this;
    }

    /**
     * Executes the provided callback if the condition is true
     * Otherwise, executes the default callback, if provided
     */
    public function when(bool $condition, Closure $callback, ?Closure $default = null): static
    {
        if ($condition) {
            $callback($this);
        } elseif ($default) {
            $default($this);
        }
        return $this;
    }

    /**
     * Wrap conditions with AND logic using a callback
     *
     * @param Closure(static):mixed $callback
     */
    public function and(Closure $callback): static
    {
        $this->beginAnd();
        $callback($this);
        $this->closeAnd();
        return $this;
    }

    /**
     * Wrap conditions with OR logic using a callback
     *
     * @param Closure(static):mixed $callback
     */
    public function or(Closure $callback): static
    {
        $this->beginOr();
        $callback($this);
        $this->closeOr();
        return $this;
    }

    /**
     * Wrap conditions with NOT logic using a callback
     *
     * Unlike and/or/xor, NOT always joins multiple conditions with AND internally.
     * To negate an OR group, nest or inside: not(fn($q) => $q->or(...))
     *
     * @param Closure(static):mixed $callback
     */
    public function not(Closure $callback): static
    {
        $this->beginNot();
        $callback($this);
        $this->closeNot();
        return $this;
    }

    /**
     * Wrap conditions with XOR logic using a callback
     *
     * Only supported by MySQL and MSSQL. Not supported by SQLite or PostgreSQL.
     *
     * @param Closure(static):mixed $callback
     */
    public function xor(Closure $callback): static
    {
        $this->beginXor();
        $callback($this);
        $this->closeXor();
        return $this;
    }

    /**
     * Magic method for sql conditions
     */
    public function __call(string $name, array $arguments): static
    {
        if ($this->conditionalBuilder === 'HAVING') {
            call_user_func_array([$this->aggregatedConditionBuilder, $name], $arguments);
        } else {
            call_user_func_array([$this->conditionBuilder, $name], $arguments);
        }

        return $this;
    }

    /**
     * Clone function ensures that cloned objects are really clones
     */
    public function __clone()
    {
        $this->conditionBuilder = clone $this->conditionBuilder;
        $this->aggregatedConditionBuilder = clone $this->aggregatedConditionBuilder;
    }

    /**
     * Values used to construct a select query
     */
    public function getValues(): array
    {
        return array_merge(
            $this->joinValues,
            $this->getConditionBuilder()->getValues(),
            $this->getAggregatedConditionBuilder()->getValues()
        );
    }
}

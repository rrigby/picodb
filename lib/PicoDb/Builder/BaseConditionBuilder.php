<?php

namespace PicoDb\Builder;

use PicoDb\Database;
use PicoDb\Table;

/**
 * Class BaseConditionBuilder
 *
 * @package PicoDb\Builder
 * @author  Frederic Guillot
 */
class BaseConditionBuilder
{
    /**
     * @var mixed[]
     */
    protected array $values = [];

    /**
     * @var string[]
     */
    protected array $conditions = [];

    /**
     * SQL embedded NOT/AND/OR/XOR conditions
     *
     * @var    LogicConditionBuilder[]
     */
    protected array $embeddedConditions = [];

    /**
     * SQL condition offset
     */
    protected int $embeddedConditionOffset = 0;

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
     * Get condition values
     *
     * @return mixed[]
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Returns true if there is some conditions
     */
    public function hasCondition(): bool
    {
        return $this->conditions !== [];
    }

    /**
     * Add custom condition
     *
     * @param  string  $sql
     */
    public function addCondition($sql): void
    {
        if ($this->embeddedConditionOffset > 0) {
            $this->embeddedConditions[$this->embeddedConditionOffset]->withCondition($sql);
        }
        else {
            $this->conditions[] = $sql;
        }
    }

    public function beginNot(): void
    {
        $this->embeddedConditionOffset++;
        $this->embeddedConditions[$this->embeddedConditionOffset] = new LogicConditionBuilder('NOT');
    }

    public function closeNot(): void
    {
        $condition = $this->embeddedConditions[$this->embeddedConditionOffset]->build();
        $this->embeddedConditionOffset--;

        if ($this->embeddedConditionOffset > 0) {
            $this->embeddedConditions[$this->embeddedConditionOffset]->withCondition($condition);
        } else {
            $this->conditions[] = $condition;
        }
    }

    /**
     * Start AND condition
     */
    public function beginAnd(): void
    {
        $this->embeddedConditionOffset++;
        $this->embeddedConditions[$this->embeddedConditionOffset] = new LogicConditionBuilder('AND');
    }

    /**
     * Close AND condition
     */
    public function closeAnd(): void
    {
        $condition = $this->embeddedConditions[$this->embeddedConditionOffset]->build();
        $this->embeddedConditionOffset--;

        if ($this->embeddedConditionOffset > 0) {
            $this->embeddedConditions[$this->embeddedConditionOffset]->withCondition($condition);
        } else {
            $this->conditions[] = $condition;
        }
    }

    /**
     * Start OR condition
     */
    public function beginOr(): void
    {
        $this->embeddedConditionOffset++;
        $this->embeddedConditions[$this->embeddedConditionOffset] = new LogicConditionBuilder('OR');
    }

    /**
     * Close OR condition
     */
    public function closeOr(): void
    {
        $condition = $this->embeddedConditions[$this->embeddedConditionOffset]->build();
        $this->embeddedConditionOffset--;

        if ($this->embeddedConditionOffset > 0) {
            $this->embeddedConditions[$this->embeddedConditionOffset]->withCondition($condition);
        } else {
            $this->conditions[] = $condition;
        }
    }

    /**
     * Start XOR condition
     *
     * Only supported by MySQL and MSSQL. Not supported by SQLite or PostgreSQL.
     */
    public function beginXor(): void
    {
        $this->embeddedConditionOffset++;
        $this->embeddedConditions[$this->embeddedConditionOffset] = new LogicConditionBuilder('XOR');
    }

    /**
     * Close OR condition
     */
    public function closeXor(): void
    {
        $condition = $this->embeddedConditions[$this->embeddedConditionOffset]->build();
        $this->embeddedConditionOffset--;

        if ($this->embeddedConditionOffset > 0) {
            $this->embeddedConditions[$this->embeddedConditionOffset]->withCondition($condition);
        } else {
            $this->conditions[] = $condition;
        }
    }

    /**
     * Equal condition
     *
     * @param  string   $column
     * @param  mixed    $value
     */
    public function eq($column, $value): void
    {
        $this->addCondition($this->db->escapeIdentifier($column).' = ?');
        $this->values[] = $value;
    }

    /**
     * Not equal condition
     *
     * @param  string   $column
     * @param  mixed    $value
     */
    public function neq($column, $value): void
    {
        $this->addCondition($this->db->escapeIdentifier($column).' != ?');
        $this->values[] = $value;
    }

    /**
     * IN condition
     *
     * @param  string   $column
     */
    public function in($column, array $values): void
    {
        if ($values !== []) {
            $this->addCondition($this->db->escapeIdentifier($column).' IN ('.implode(', ', array_fill(0, count($values), '?')).')');
            $this->values = array_merge($this->values, $values);
        } else {
            $this->addCondition('0 = 1');
        }
    }

    /**
     * IN condition with a subquery
     *
     * @param  string   $column
     */
    public function inSubquery($column, Table $subquery): void
    {
        $this->addCondition($this->db->escapeIdentifier($column).' IN ('.$subquery->buildSelectQuery().')');
        $this->values = array_merge($this->values, $subquery->getValues());
    }

    /**
     * NOT IN condition
     *
     * @param  string   $column
     */
    public function notIn($column, array $values): void
    {
        if ($values !== []) {
            $this->addCondition($this->db->escapeIdentifier($column).' NOT IN ('.implode(', ', array_fill(0, count($values), '?')).')');
            $this->values = array_merge($this->values, $values);
        }
    }

    /**
     * NOT IN condition with a subquery
     *
     * @param  string   $column
     */
    public function notInSubquery($column, Table $subquery): void
    {
        $this->addCondition($this->db->escapeIdentifier($column).' NOT IN ('.$subquery->buildSelectQuery().')');
        $this->values = array_merge($this->values, $subquery->getValues());
    }

    /**
     * LIKE condition
     *
     * @param  string   $column
     * @param  mixed    $value
     */
    public function like($column, $value): void
    {
        $this->addCondition($this->db->escapeIdentifier($column).' '.$this->db->getDriver()->getOperator('LIKE').' ?');
        $this->values[] = $value;
    }

    /**
     * ILIKE condition
     *
     * @param  string   $column
     * @param  mixed    $value
     */
    public function ilike($column, $value): void
    {
        $this->addCondition($this->db->escapeIdentifier($column).' '.$this->db->getDriver()->getOperator('ILIKE').' ?');
        $this->values[] = $value;
    }

    /**
     * NOT LIKE condition
     *
     * @param $column
     * @param $value
     */
    public function notLike($column, $value): void
    {
        $this->addCondition($this->db->escapeIdentifier($column).' NOT LIKE ?');
        $this->values[] = $value;
    }

    /**
     * Greater than condition
     *
     * @param  string   $column
     * @param  mixed    $value
     */
    public function gt($column, $value): void
    {
        $this->addCondition($this->db->escapeIdentifier($column).' > ?');
        $this->values[] = $value;
    }

    /**
     * Greater than condition with subquery
     *
     * @param  string   $column
     */
    public function gtSubquery($column, Table $subquery): void
    {
        $this->addCondition($this->db->escapeIdentifier($column).' > ('.$subquery->buildSelectQuery().')');
        $this->values = array_merge($this->values, $subquery->getValues());
    }

    /**
     * Lower than condition
     *
     * @param  string   $column
     * @param  mixed    $value
     */
    public function lt($column, $value): void
    {
        $this->addCondition($this->db->escapeIdentifier($column).' < ?');
        $this->values[] = $value;
    }

    /**
     * Lower than condition with subquery
     *
     * @param  string   $column
     */
    public function ltSubquery($column, Table $subquery): void
    {
        $this->addCondition($this->db->escapeIdentifier($column).' < ('.$subquery->buildSelectQuery().')');
        $this->values = array_merge($this->values, $subquery->getValues());
    }

    /**
     * Greater than or equals condition
     *
     * @param  string   $column
     * @param  mixed    $value
     */
    public function gte($column, $value): void
    {
        $this->addCondition($this->db->escapeIdentifier($column).' >= ?');
        $this->values[] = $value;
    }

    /**
     * Greater than or equal condition with subquery
     *
     * @param  string   $column
     */
    public function gteSubquery($column, Table $subquery): void
    {
        $this->addCondition($this->db->escapeIdentifier($column).' >= ('.$subquery->buildSelectQuery().')');
        $this->values = array_merge($this->values, $subquery->getValues());
    }

    /**
     * Lower than or equals condition
     *
     * @param  string   $column
     * @param  mixed    $value
     */
    public function lte($column, $value): void
    {
        $this->addCondition($this->db->escapeIdentifier($column).' <= ?');
        $this->values[] = $value;
    }

    /**
     * Lower than or equal condition with subquery
     *
     * @param  string   $column
     */
    public function lteSubquery($column, Table $subquery): void
    {
        $this->addCondition($this->db->escapeIdentifier($column).' <= ('.$subquery->buildSelectQuery().')');
        $this->values = array_merge($this->values, $subquery->getValues());
    }

    /**
     * BETWEEN operator
     *
     * @param $column
     * @param $lowValue
     * @param $highValue
     */
    public function between($column, $lowValue, $highValue): void
    {
        $this->addCondition($this->db->escapeIdentifier($column).' BETWEEN ? AND ?');
        $this->values[] = $lowValue;
        $this->values[] = $highValue;
    }

    /**
     * NOT BETWEEN operator
     *
     * @param $column
     * @param $lowValue
     * @param $highValue
     */
    public function notBetween($column, $lowValue, $highValue): void
    {
        $this->addCondition($this->db->escapeIdentifier($column).' NOT BETWEEN ? AND ?');
        $this->values[] = $lowValue;
        $this->values[] = $highValue;
    }

    /**
     * IS NULL condition
     *
     * @param  string   $column
     */
    public function isNull($column): void
    {
        $this->addCondition($this->db->escapeIdentifier($column).' IS NULL');
    }

    /**
     * IS NOT NULL condition
     *
     * @param  string   $column
     */
    public function notNull($column): void
    {
        $this->addCondition($this->db->escapeIdentifier($column).' IS NOT NULL');
    }

    /**
     * Normalize a JSON path to JSONPath format ($.key).
     * Accepts 'key', '$.key', 'key1.key2', or '$.key1.key2'.
     */
    private function normalizeJsonPath(string $path): string
    {
        return str_starts_with($path, '$') ? $path : '$.'.$path;
    }

    /**
     * JSON field equality condition
     *
     * Compares a scalar value extracted from a JSON column at the given JSONPath.
     *
     * @param  string  $column  Column name
     * @param  string  $path    JSONPath expression (e.g. '$.key' or '$.key1.key2')
     * @param  mixed   $value   Value to compare against
     */
    public function jsonEq(string $column, string $path, $value): void
    {
        $this->addCondition($this->db->getDriver()->buildJsonExtractCondition(
            $this->db->escapeIdentifier($column),
            $this->normalizeJsonPath($path),
            '='
        ));
        $this->values[] = $value;
    }

    /**
     * JSON field inequality condition
     *
     * @param  string  $column  Column name
     * @param  string  $path    JSONPath expression (e.g. 'key' or '$.key' or '$.key1.key2')
     * @param  mixed   $value   Value to compare against
     */
    public function jsonNeq(string $column, string $path, $value): void
    {
        $this->addCondition($this->db->getDriver()->buildJsonExtractCondition(
            $this->db->escapeIdentifier($column),
            $this->normalizeJsonPath($path),
            '!='
        ));
        $this->values[] = $value;
    }

    /**
     * JSON array containment condition
     *
     * Checks that all elements of $values exist in the JSON array stored in $column,
     * optionally at a JSONPath within the column.
     *
     * @param  string      $column  Column name
     * @param  array       $values  Values that must all be present in the JSON array
     * @param  string|null $path    JSONPath expression, or null to target the column directly
     */
    public function jsonContains(string $column, array $values, ?string $path = null): void
    {
        if ($values === []) {
            $this->addCondition('0 = 1');
            return;
        }

        [$sql, $bindings] = $this->db->getDriver()->buildJsonContainsCondition(
            $this->db->escapeIdentifier($column),
            $path !== null ? $this->normalizeJsonPath($path) : null,
            $values
        );

        $this->addCondition($sql);
        $this->values = array_merge($this->values, $bindings);
    }

    /**
     * JSON array non-containment condition
     *
     * The inverse of jsonContains — matches rows where the JSON array does NOT
     * contain all of the given values.
     *
     * @param  string      $column  Column name
     * @param  array       $values  Values that must not all be present in the JSON array
     * @param  string|null $path    JSONPath expression, or null to target the column directly
     */
    public function jsonNotContains(string $column, array $values, ?string $path = null): void
    {
        if ($values === []) {
            return;
        }

        [$sql, $bindings] = $this->db->getDriver()->buildJsonContainsCondition(
            $this->db->escapeIdentifier($column),
            $path !== null ? $this->normalizeJsonPath($path) : null,
            $values
        );

        $this->addCondition('NOT ('.$sql.')');
        $this->values = array_merge($this->values, $bindings);
    }
}

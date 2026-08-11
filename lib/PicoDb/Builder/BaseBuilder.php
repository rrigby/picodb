<?php

declare(strict_types=1);

namespace PicoDb\Builder;

use PicoDb\Database;

/**
 * Class BaseBuilder
 *
 * @package PicoDb\Builder
 * @author  Frederic Guillot
 */
abstract class BaseBuilder
{
    protected string $table = '';

    /**
     * @var string[]
     */
    protected array $columns = [];

    /**
     * InsertBuilder constructor
     */
    public function __construct(protected Database $db, protected ConditionBuilder $conditionBuilder)
    {
    }

    /**
     * Get object instance
     */
    public static function getInstance(Database $db, ConditionBuilder $condition): static
    {
        return new static($db, $condition);
    }

    /**
     * Set table name
     */
    public function withTable(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Set columns name
     *
     * @param  string[] $columns
     */
    public function withColumns(array $columns): static
    {
        $this->columns = $columns;
        return $this;
    }
}

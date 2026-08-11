<?php

namespace PicoDb\Builder;

/**
 * Class UpdateBuilder
 *
 * @package PicoDb\Builder
 * @author  Frederic Guillot
 */
class UpdateBuilder extends BaseBuilder implements BuilderInterface
{
    /**
     * @var string[]
     */
    protected array $sumColumns = [];

    /**
     * Set columns name
     *
     * @param  string[] $columns
     */
    public function withSumColumns(array $columns): static
    {
        $this->sumColumns = $columns;
        return $this;
    }

    /**
     * Build SQL
     */
    public function build(): string
    {
        $columns = [];

        foreach ($this->columns as $column) {
            $columns[] = $this->db->escapeIdentifier($column).'=?';
        }

        foreach ($this->sumColumns as $column) {
            $columns[] = $this->db->escapeIdentifier($column).'='.$this->db->escapeIdentifier($column).' + ?';
        }

        return sprintf(
            'UPDATE %s SET %s %s',
            $this->db->escapeIdentifier($this->table),
            implode(', ', $columns),
            $this->conditionBuilder->build()
        );
    }
}

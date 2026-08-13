<?php

declare(strict_types=1);

namespace PicoDb\Builder;

/**
 * Class LogicConditionBuilder
 *
 * @package PicoDb\Builder
 * @author  Frederic Guillot
 */
class LogicConditionBuilder implements BuilderInterface
{
    /**
     * @var string[]
     */
    protected array $conditions = [];

    public function __construct(private string $type)
    {
    }

    /**
     * Add new condition
     */
    public function withCondition(string $condition): static
    {
        $this->conditions[] = $condition;
        return $this;
    }

    /**
     * Build SQL
     */
    public function build(): string
    {
        if ($this->type === 'NOT') {
            if (count($this->conditions) === 1) {
                return 'NOT ' . $this->conditions[0];
            }

            return 'NOT (' . implode(' AND ', $this->conditions) . ')';
        }

        return '('.implode(' '. $this->type .' ', $this->conditions).')';
    }
}

<?php

namespace PicoDb\Builder;

use PicoDb\Database;

/**
 * Class AggregatedConditionBuilder
 *
 * @package PicoDb\Builder
 * @author  Frederic Guillot
 */
class AggregatedConditionBuilder extends BaseConditionBuilder implements BuilderInterface
{
    /**
     * Constructor
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Build the SQL aggregated condition
     */
    public function build(): string
    {
        return empty($this->conditions) ? '' : ' HAVING '.implode(' AND ', $this->conditions);
    }
}

<?php

declare(strict_types=1);

namespace PicoDb\Builder;

use PicoDb\Database;

/**
 * Class ConditionBuilder
 *
 * @package PicoDb\Builder
 * @author  Frederic Guillot
 */
class ConditionBuilder extends BaseConditionBuilder implements BuilderInterface
{
    /**
     * Constructor
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Build the SQL condition
     */
    public function build(): string
    {
        return $this->conditions === [] ? '' : ' WHERE '.implode(' AND ', $this->conditions);
    }
}

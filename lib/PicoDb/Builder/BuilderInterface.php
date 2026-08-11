<?php

declare(strict_types=1);

namespace PicoDb\Builder;

/**
 * Class BuilderInterface
 *
 * @package PicoDb\Builder
 * @author  Frederic Guillot
 */
interface BuilderInterface
{
    /**
     * Build the SQL
     */
    public function build();
}

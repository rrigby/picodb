<?php

declare(strict_types=1);

namespace PicoDb;

use LogicException;
use PicoDb\Driver\Mssql;
use PicoDb\Driver\Mysql;
use PicoDb\Driver\Postgres;
use PicoDb\Driver\Sqlite;

/**
 * Class DriverFactory
 *
 * @package PicoDb
 * @author  Frederic Guillot
 */
class DriverFactory
{
    /**
     * Get database driver from settings or environment URL
     *
     * @param array<string, mixed> $settings
     */
    public static function getDriver(array $settings): Sqlite|Mssql|Mysql|Postgres
    {
        if (! isset($settings['driver'])) {
            throw new LogicException('You must define a database driver');
        }

        return match ($settings['driver']) {
            'sqlite' => new Sqlite($settings),
            'mssql' => new Mssql($settings),
            'mysql' => new Mysql($settings),
            'postgres' => new Postgres($settings),
            default => throw new LogicException('This database driver is not supported'),
        };
    }
}

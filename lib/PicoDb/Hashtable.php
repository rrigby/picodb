<?php

declare(strict_types=1);

namespace PicoDb;

use PDO;

/**
 * HashTable (key/value)
 *
 * @package PicoDb
 * @author  Frederic Guillot
 * @author  Mathias Kresin
 */
class Hashtable extends Table
{
    private string $keyColumn = 'key';

    private string $valueColumn = 'value';

    /**
     * Set the key column
     */
    public function columnKey(string $column): static
    {
        $this->keyColumn = $column;
        return $this;
    }

    /**
     * Set the value column
     */
    public function columnValue(string $column): static
    {
        $this->valueColumn = $column;
        return $this;
    }

    /**
     * Insert or update
     */
    public function put(array $hashmap): bool
    {
        return $this->db->getDriver()->upsert($this->getName(), $this->keyColumn, $this->valueColumn, $hashmap);
    }

    /**
     * Hashmap result [ [column1 => column2], [], ...]
     *
     * @return array<array-key, scalar|null>
     */
    public function get(): array
    {
        /** @var array<array-key, scalar|null> $hashmap */
        $hashmap = [];

        // setup where condition
        if (func_num_args() > 0) {
            $this->in($this->keyColumn, func_get_args());
        }

        // setup to select columns in case that there are more than two
        $this->columns($this->keyColumn, $this->valueColumn);

        $rq = $this->db->execute($this->buildSelectQuery(), $this->conditionBuilder->getValues());
        /** @var list<array{0: scalar|null, 1: scalar|null}> $rows */
        $rows = $rq->fetchAll(PDO::FETCH_NUM);

        foreach ($rows as [$key, $value]) {
            if ($key === null) {
                continue;
            }
            if (is_bool($key)) {
                continue;
            }
            if (is_float($key)) {
                continue;
            }
            $hashmap[$key] = $value;
        }

        return $hashmap;
    }

    /**
     * Shortcut method to get a hashmap result
     *
     * @param  string  $key    Key column
     * @param  string  $value  Value column
     */
    public function getAll(string $key, string $value): array
    {
        $this->keyColumn = $key;
        $this->valueColumn = $value;
        return $this->get();
    }
}

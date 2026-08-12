<?php

declare(strict_types=1);

namespace PicoDb;

use PDO;
use PicoDb\Builder\InsertBuilder;
use PicoDb\Builder\UpdateBuilder;

/**
 * Handle Large Objects (LOBs)
 *
 * @package PicoDb
 * @author  Frederic Guillot
 */
class LargeObject extends Table
{
    /**
     * Fetch large object as file descriptor
     *
     * This method is not compatible with Sqlite and Mysql (return a string instead of resource)
     *
     * @param  string $column
     * @return resource|string|null
     */
    public function findOneColumnAsStream($column)
    {
        $this->limit(1);
        $this->columns($column);

        $rq = $this->db->getStatementHandler()
            ->withSql($this->buildSelectQuery())
            ->withPositionalParams($this->conditionBuilder->getValues())
            ->execute();

        $rq->bindColumn($column, $fd, PDO::PARAM_LOB);
        $rq->fetch(PDO::FETCH_BOUND);

        return $fd;
    }

    /**
     * Fetch large object as string
     *
     * @param  string $column
     */
    public function findOneColumnAsString($column): string
    {
        $fd = $this->findOneColumnAsStream($column);

        if (is_string($fd)) {
            return $fd;
        }

        if ($fd === null) {
            return '';
        }

        $contents = stream_get_contents($fd);
        return $contents === false ? '' : $contents;
    }

    /**
     * Insert large object from stream
     *
     * @param  string           $blobColumn
     * @param  resource|string  $blobDescriptor
     */
    public function insertFromStream($blobColumn, &$blobDescriptor, array $data = []): bool
    {
        $columns = array_merge([$blobColumn], array_keys($data));
        $this->db->startTransaction();

        $this->db->getStatementHandler()
            ->withSql(InsertBuilder::getInstance($this->db, $this->conditionBuilder)
                ->withTable($this->name)
                ->withColumns($columns)
                ->build()
            )
            ->withNamedParams($data)
            ->withLobParam($blobColumn, $blobDescriptor)
            ->execute();

        $this->db->closeTransaction();

        return true;
    }

    /**
     * Insert large object from file
     *
     * @param  string $blobColumn
     * @param  string $filename
     */
    public function insertFromFile($blobColumn, $filename, array $data = []): bool
    {
        $fp = fopen($filename, 'rb');

        if ($fp === false) {
            return false;
        }

        $result = $this->insertFromStream($blobColumn, $fp, $data);

        if (is_resource($fp)) {
            fclose($fp);
        }

        return $result;
    }

    /**
     * Insert large object from string
     *
     * @param  string $blobColumn
     * @param  string $blobData
     */
    public function insertFromString($blobColumn, $blobData, array $data = []): bool
    {
        return $this->insertFromStream($blobColumn, $blobData, $data);
    }

    /**
     * Update large object from stream
     *
     * @param  string   $blobColumn
     * @param  resource $blobDescriptor
     */
    public function updateFromStream($blobColumn, &$blobDescriptor, array $data = []): bool
    {
        $values = array_merge(array_values($data), $this->conditionBuilder->getValues());
        $columns = array_merge([$blobColumn], array_keys($data));

        $this->db->startTransaction();

        $this->db->getStatementHandler()
            ->withSql(UpdateBuilder::getInstance($this->db, $this->conditionBuilder)
                ->withTable($this->name)
                ->withColumns($columns)
                ->build()
            )
            ->withPositionalParams($values)
            ->withLobParam($blobColumn, $blobDescriptor)
            ->execute();

        $this->db->closeTransaction();

        return true;
    }

    /**
     * Update large object from file
     *
     * @param  string $blobColumn
     * @param  string $filename
     */
    public function updateFromFile($blobColumn, $filename, array $data = []): bool
    {
        $fp = fopen($filename, 'r');

        if ($fp === false) {
            return false;
        }

        $result = $this->updateFromStream($blobColumn, $fp, $data);

        if (is_resource($fp)) {
            fclose($fp);
        }

        return $result;
    }
}

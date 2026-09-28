<?php

declare(strict_types=1);

namespace PicoDb;

use PHPUnit\Framework\TestCase;

class WhereRawTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        $this->db = new Database(['driver' => 'sqlite', 'filename' => ':memory:']);

        $this->db->getConnection()->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT, price INTEGER)');
        $this->db->getConnection()->exec('CREATE TABLE tags (item_id INTEGER, tag TEXT)');

        $this->db->table('items')->insert(['id' => 1, 'name' => 'apple', 'price' => 10]);
        $this->db->table('items')->insert(['id' => 2, 'name' => 'banana', 'price' => 20]);
        $this->db->table('items')->insert(['id' => 3, 'name' => 'cherry', 'price' => 30]);

        $this->db->table('tags')->insert(['item_id' => 1, 'tag' => 'fruit']);
        $this->db->table('tags')->insert(['item_id' => 2, 'tag' => 'fruit']);
        $this->db->table('tags')->insert(['item_id' => 2, 'tag' => 'yellow']);
    }

    public function testWhereRaw(): void
    {
        $table = $this->db->table('items')->eq('name', 'apple')->whereRaw('price BETWEEN ? AND ?', [5, 15]);

        $this->assertSame('SELECT * FROM "items"   WHERE "name" = ? AND (price BETWEEN ? AND ?)', $table->buildSelectQuery());
        $this->assertSame(['apple', 5, 15], $table->getValues());
        $this->assertSame([['id' => 1, 'name' => 'apple', 'price' => 10]], $table->findAll());
    }

    public function testWhereRawWithoutValues(): void
    {
        $table = $this->db->table('items')->whereRaw('price > 15');

        $this->assertSame('SELECT * FROM "items"   WHERE (price > 15)', $table->buildSelectQuery());
        $this->assertSame([], $table->getValues());
    }

    public function testWhereRawInsideAnOrGroupKeepsValueOrder(): void
    {
        $table = $this->db->table('items')
            ->gt('price', 5)
            ->beginOr()
            ->whereRaw('name = ?', ['banana'])
            ->eq('name', 'cherry')
            ->closeOr()
            ->neq('id', 99);

        $this->assertSame(
            'SELECT * FROM "items"   WHERE "price" > ? AND ((name = ?) OR "name" = ?) AND "id" != ?',
            $table->buildSelectQuery()
        );
        $this->assertSame([5, 'banana', 'cherry', 99], $table->getValues());
        $this->assertSame(['banana', 'cherry'], array_column($table->findAll(), 'name'));
    }

    public function testHavingRaw(): void
    {
        $table = $this->db->table('tags')
            ->columns('item_id')
            ->groupBy('item_id')
            ->having()
            ->whereRaw('MAX(tag) = ?', ['yellow']);

        $this->assertSame('SELECT "item_id" FROM "tags"   GROUP BY "item_id"  HAVING (MAX(tag) = ?)', $table->buildSelectQuery());
        $this->assertSame([['item_id' => 2]], $table->findAll());
    }
}

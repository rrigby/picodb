<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PicoDb\Database;

use PicoDb\SQLException;

class PostgresDatabaseTest extends TestCase
{
    private Database $db;

    public function setUp(): void
    {
        $this->db = new Database(['driver' => 'postgres', 'hostname' => getenv('POSTGRES_HOST'), 'username' => 'root', 'password' => 'rootpassword', 'database' => 'picodb']);
        $this->db->getConnection()->exec('DROP TABLE IF EXISTS foobar');
        $this->db->getConnection()->exec('DROP TABLE IF EXISTS schema_version');
    }

    public function testEscapeIdentifer(): void
    {
        $this->assertEquals('"a"', $this->db->escapeIdentifier('a'));
        $this->assertEquals('a.b', $this->db->escapeIdentifier('a.b'));
        $this->assertEquals('"c"."a"', $this->db->escapeIdentifier('a', 'c'));
        $this->assertEquals('a.b', $this->db->escapeIdentifier('a.b', 'c'));
        $this->assertEquals('SELECT COUNT(*) FROM test', $this->db->escapeIdentifier('SELECT COUNT(*) FROM test'));
        $this->assertEquals('SELECT COUNT(*) FROM test', $this->db->escapeIdentifier('SELECT COUNT(*) FROM test', 'b'));
        $this->assertEquals('*', $this->db->escapeIdentifier('*'));
        $this->assertEquals('"c".*', $this->db->escapeIdentifier('*', 'c'));
    }

    public function testEscapeIdentiferList(): void
    {
        $this->assertEquals(['"c"."a"', '"c"."b"'], $this->db->escapeIdentifierList(['a', 'b'], 'c'));
        $this->assertEquals(['"a"', 'd.b'], $this->db->escapeIdentifierList(['a', 'd.b']));
    }

    public function testThatPreparedStatementWorks(): void
    {
        $this->db->getConnection()->exec('CREATE TABLE foobar (id serial PRIMARY KEY, something TEXT)');
        $this->db->execute('INSERT INTO foobar (something) VALUES (?)', ['a']);
        $this->assertEquals(1, $this->db->getLastId());
        $this->assertEquals('a', $this->db->execute('SELECT something FROM foobar WHERE something=?', ['a'])->fetchColumn());
    }

    public function testThatBooleanParamsBindAgainstNotNullColumn(): void
    {
        $this->db->getConnection()->exec('CREATE TABLE foobar (id serial PRIMARY KEY, flag BOOLEAN NOT NULL)');

        $this->db->execute('INSERT INTO foobar (flag) VALUES (?)', [true]);
        $this->db->execute('INSERT INTO foobar (flag) VALUES (?)', [false]);

        $rows = $this->db->execute('SELECT flag FROM foobar ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame([
            ['flag' => true],
            ['flag' => false],
        ], $rows);
    }

    public function testThatTableInsertBindsBooleanNamedParams(): void
    {
        $this->db->getConnection()->exec('CREATE TABLE foobar (id serial PRIMARY KEY, flag BOOLEAN NOT NULL)');

        $this->db->table('foobar')->insert(['flag' => true]);
        $this->db->table('foobar')->insert(['flag' => false]);

        $this->assertSame([true, false], $this->db->table('foobar')->asc('id')->findAllByColumn('flag'));
    }

    public function testThatBooleanParamsAreRejectedByIntegerColumn(): void
    {
        $this->db->getConnection()->exec('CREATE TABLE foobar (id serial PRIMARY KEY, flag SMALLINT NOT NULL)');

        // Bools bind as PARAM_BOOL ('t'/'f'), so non-boolean columns need an explicit cast
        $this->expectException(SQLException::class);
        $this->db->execute('INSERT INTO foobar (flag) VALUES (?)', [true]);
    }

    public function testThatIntParamsBindWithCorrectPdoType(): void
    {
        $this->db->getConnection()->exec('CREATE TABLE foobar (id serial PRIMARY KEY, count INTEGER NOT NULL, note TEXT)');

        $this->db->execute('INSERT INTO foobar (count, note) VALUES (?, ?)', [42, 'a']);
        $this->db->execute('INSERT INTO foobar (count, note) VALUES (?, ?)', [0, 'b']);

        $this->assertSame([42, 0], $this->db->table('foobar')->asc('id')->findAllByColumn('count'));
        // Ints compared against a text column still work as Postgres infers the type
        $this->assertSame('b', $this->db->execute('SELECT note FROM foobar WHERE note = ? OR count = ?', ['b', 0])->fetchColumn());
    }

    public function testThatTableInsertBindsTypedNamedParams(): void
    {
        $this->db->getConnection()->exec('CREATE TABLE foobar (id serial PRIMARY KEY, flag BOOLEAN, count INTEGER, note TEXT)');

        $this->db->table('foobar')->insert(['flag' => true, 'count' => 1, 'note' => 'yes']);
        $this->db->table('foobar')->insert(['flag' => false, 'count' => 2, 'note' => 'no']);
        $this->db->table('foobar')->insert(['flag' => null, 'count' => null, 'note' => null]);

        $rows = $this->db->execute('SELECT flag, count, note FROM foobar ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame([
            ['flag' => true, 'count' => 1, 'note' => 'yes'],
            ['flag' => false, 'count' => 2, 'note' => 'no'],
            ['flag' => null, 'count' => null, 'note' => null],
        ], $rows);
    }

    public function testBadSQLQuery(): void
    {
        $this->expectException(SQLException::class);

        $this->db->execute('INSERT INTO foobar');
    }

    public function testDuplicateKey(): void
    {
        $this->expectException(SQLException::class);

        $this->db->getConnection()->exec('CREATE TABLE foobar (something TEXT UNIQUE)');

        $this->assertNotFalse($this->db->execute('INSERT INTO foobar (something) VALUES (?)', ['a']));
        $this->db->execute('INSERT INTO foobar (something) VALUES (?)', ['a']);
    }

    public function testThatTransactionReturnsAValue(): void
    {
        $this->assertEquals('a', $this->db->transaction(function (Database $db): string|int|null|false {
            $db->getConnection()->exec('CREATE TABLE foobar (something TEXT UNIQUE)');
            $db->execute('INSERT INTO foobar (something) VALUES (?)', ['a']);

            return $db->execute('SELECT something FROM foobar WHERE something=?', ['a'])->fetchColumn();
        }));
    }

    public function testThatTransactionReturnsTrue(): void
    {
        $this->assertTrue($this->db->transaction(function (Database $db): void {
            $db->getConnection()->exec('CREATE TABLE foobar (something TEXT UNIQUE)');
            $db->execute('INSERT INTO foobar (something) VALUES (?)', ['a']);
        }));
    }

    public function testThatTransactionThrowExceptionWhenRollbacked(): void
    {
        $this->expectException(SQLException::class);

        $this->assertFalse($this->db->transaction(function (Database $db): void {
            $db->getConnection()->exec('CREATE TABL');
        }));
    }

    public function testThatTransactionReturnsFalseWhithDuplicateKey(): void
    {
        $this->expectException(SQLException::class);

        $this->db->transaction(function (Database $db): bool {
            $db->getConnection()->exec('CREATE TABLE foobar (something TEXT UNIQUE)');
            $r1 = $db->execute('INSERT INTO foobar (something) VALUES (?)', ['a']);
            $r2 = $db->execute('INSERT INTO foobar (something) VALUES (?)', ['a']);
            return $r1 && $r2;
        });
    }
}

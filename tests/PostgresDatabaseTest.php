<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PicoDb\SQLException;

use PicoDb\Database;

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

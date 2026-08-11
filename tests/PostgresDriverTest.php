<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use PicoDb\Driver\Postgres;

class PostgresDriverTest extends TestCase
{
    private Postgres $driver;

    public function setUp(): void
    {
        $this->driver = new Postgres(['hostname' => getenv('POSTGRES_HOST'), 'username' => 'root', 'password' => 'rootpassword', 'database' => 'picodb']);
        $this->driver->getConnection()->exec('DROP TABLE IF EXISTS foo');
        $this->driver->getConnection()->exec('DROP TABLE IF EXISTS foobar');
        $this->driver->getConnection()->exec('DROP TABLE IF EXISTS schema_version');
    }

    public function tearDown(): void
    {
        $this->driver->closeConnection();
    }

    public function testMissingRequiredParameter(): void
    {
        $this->expectException(LogicException::class);

        new Postgres([]);
    }

    public function testDuplicateKeyError(): void
    {
        $this->assertFalse($this->driver->isDuplicateKeyError(1234));
        $this->assertTrue($this->driver->isDuplicateKeyError(23505));
        $this->assertTrue($this->driver->isDuplicateKeyError(23503));
    }

    public function testOperator(): void
    {
        $this->assertEquals('LIKE', $this->driver->getOperator('LIKE'));
        $this->assertEquals('ILIKE', $this->driver->getOperator('ILIKE'));
        $this->assertEquals('', $this->driver->getOperator('FOO'));
    }

    public function testSchemaVersion(): void
    {
        $this->assertEquals(0, $this->driver->getSchemaVersion());

        $this->driver->setSchemaVersion(1);
        $this->assertEquals(1, $this->driver->getSchemaVersion());

        $this->driver->setSchemaVersion(42);
        $this->assertEquals(42, $this->driver->getSchemaVersion());
    }

    public function testLastInsertId(): void
    {
        $this->assertEquals(0, $this->driver->getLastId());

        $this->driver->getConnection()->exec('CREATE TABLE foobar (id serial PRIMARY KEY, something TEXT)');
        $this->driver->getConnection()->exec('INSERT INTO foobar (something) VALUES (1)');

        $this->assertEquals(1, $this->driver->getLastId());
    }

    public function testEscape(): void
    {
        $this->assertEquals('"foobar"', $this->driver->escape('foobar'));
    }

//    public function testDatabaseVersion()
//    {
//        $this->assertStringStartsWith('11.', $this->driver->getDatabaseVersion());
//    }

    public function testExplainWithSingleQuoteValue(): void
    {
        $this->driver->getConnection()->exec('CREATE TABLE foobar (name TEXT)');
        $result = $this->driver->explain('SELECT * FROM "foobar" WHERE "name" = ?', ["O'Brien"]);
        $this->assertIsArray($result);
    }
}

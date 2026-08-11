<?php

use PHPUnit\Framework\TestCase;
use PicoDb\Driver\Base;
use PicoDb\Driver\Sqlite;

class SqliteDriverTest extends TestCase
{
    private Sqlite $driver;

    public function setUp(): void
    {
        $this->driver = new Sqlite(['filename' => ':memory:']);
    }

    public function testGetConnectionReturnsPdo(): void
    {
        $this->assertInstanceOf(PDO::class, $this->driver->getConnection());
    }

    public function testGetConnectionThrowsWhenNotInitialized(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The database connection is not established.');

        $reflection = new ReflectionProperty(Base::class, 'pdo');
        $reflection->setAccessible(true);
        $reflection->setValue($this->driver, null);

        $this->driver->getConnection();
    }

    public function testMissingRequiredParameter(): void
    {
        $this->expectException(LogicException::class);

        new Sqlite([]);
    }

    public function testDuplicateKeyError(): void
    {
        $this->assertFalse($this->driver->isDuplicateKeyError(1234));
        $this->assertTrue($this->driver->isDuplicateKeyError(23000));
    }

    public function testOperator(): void
    {
        $this->assertEquals('LIKE', $this->driver->getOperator('LIKE'));
        $this->assertEquals('LIKE', $this->driver->getOperator('ILIKE'));
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

        $this->driver->getConnection()->exec('CREATE TABLE foobar (id INTEGER PRIMARY KEY, something TEXT)');
        $this->driver->getConnection()->exec('INSERT INTO foobar (something) VALUES (1)');

        $this->assertEquals(1, $this->driver->getLastId());
    }

    public function testEscape(): void
    {
        $this->assertEquals('"foobar"', $this->driver->escape('foobar'));
    }

    public function testDatabaseVersion(): void
    {
        $this->assertStringStartsWith('3.', $this->driver->getDatabaseVersion());
    }

    public function testExplainWithSingleQuoteValue(): void
    {
        $this->driver->getConnection()->exec('CREATE TABLE foobar (name TEXT)');
        $result = $this->driver->explain('SELECT * FROM "foobar" WHERE "name" = ?', ["O'Brien"]);
        $this->assertIsArray($result);
    }
}

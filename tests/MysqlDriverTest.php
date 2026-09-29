<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use PicoDb\Driver\Mysql;

class MysqlDriverTest extends TestCase
{
    private Mysql $driver;

    public function setUp(): void
    {
        $this->driver = new Mysql(['hostname' => getenv('MYSQL_HOST'), 'username' => 'root', 'password' => 'rootpassword', 'database' => 'picodb']);
        $this->driver->getConnection()->exec('CREATE DATABASE IF NOT EXISTS `picodb`');
        $this->driver->getConnection()->exec('DROP TABLE IF EXISTS foobar');
        $this->driver->getConnection()->exec('DROP TABLE IF EXISTS schema_version');
    }

    public function testMissingRequiredParameter(): void
    {
        $this->expectException(LogicException::class);

        new Mysql([]);
    }

    public function testBuildOptionsIncludesSslSettings(): void
    {
        $driver = new class (['hostname' => getenv('MYSQL_HOST'), 'username' => 'root', 'password' => 'rootpassword', 'database' => 'picodb']) extends Mysql {
            public function options(array $settings): array
            {
                return $this->buildOptions($settings);
            }

            public function attribute(string $name): int
            {
                return $this->getMysqlAttribute($name);
            }
        };

        $options = $driver->options([
            'charset' => 'utf8mb4',
            'ssl_key' => '/key.pem',
            'ssl_cert' => '/cert.pem',
            'ssl_ca' => '/ca.pem',
            'verify_server_cert' => false,
        ]);

        $this->assertCount(5, $options);
        $this->assertSame('SET sql_mode = STRICT_ALL_TABLES, NAMES utf8mb4', $options[$driver->attribute('INIT_COMMAND')]);
        $this->assertSame('/key.pem', $options[$driver->attribute('SSL_KEY')]);
        $this->assertSame('/cert.pem', $options[$driver->attribute('SSL_CERT')]);
        $this->assertSame('/ca.pem', $options[$driver->attribute('SSL_CA')]);
        $this->assertFalse($options[$driver->attribute('SSL_VERIFY_SERVER_CERT')]);
    }

    public function testDuplicateKeyError(): void
    {
        $this->assertFalse($this->driver->isDuplicateKeyError(1234));
        $this->assertTrue($this->driver->isDuplicateKeyError(23000));
    }

    public function testOperator(): void
    {
        $this->assertEquals('LIKE BINARY', $this->driver->getOperator('LIKE'));
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

        $this->driver->getConnection()->exec('CREATE TABLE foobar (id INT AUTO_INCREMENT NOT NULL, something TEXT, PRIMARY KEY (id)) ENGINE=InnoDB');
        $this->driver->getConnection()->exec('INSERT INTO foobar (something) VALUES (1)');

        $this->assertEquals(1, $this->driver->getLastId());
    }

    public function testEscape(): void
    {
        $this->assertEquals('`foobar`', $this->driver->escape('foobar'));
    }

    //    public function testDatabaseVersion()
    //    {
    //        $this->assertStringStartsWith('5.', $this->driver->getDatabaseVersion());
    //    }

    public function testExplainWithSingleQuoteValue(): void
    {
        $this->driver->getConnection()->exec('CREATE TABLE foobar (name VARCHAR(100))');
        $result = $this->driver->explain('SELECT * FROM `foobar` WHERE `name` = ?', ["O'Brien"]);
        $this->assertIsArray($result);
    }
}

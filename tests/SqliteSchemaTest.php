<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PicoDb\Database;

require_once __DIR__.'/SchemaFixture.php';

class SqliteSchemaTest extends TestCase
{
    private Database $db;

    public function setUp(): void
    {
        $this->db = new Database(['driver' => 'sqlite', 'filename' => ':memory:']);
    }

    public function testMigrations(): void
    {
        $this->assertTrue($this->db->schema()->check(2));
        $this->assertEquals(2, $this->db->getDriver()->getSchemaVersion());
    }

    public function testFailedMigrations(): void
    {
        $this->assertFalse($this->db->schema()->check(3));
        $this->assertEquals(2, $this->db->getDriver()->getSchemaVersion());

        $logs = $this->db->getLogMessages();
        $this->assertNotEmpty($logs);
        $this->assertEquals('Running migration \Schema\version_1', $logs[0]);
        $this->assertEquals('Running migration \Schema\version_2', $logs[1]);
        $this->assertEquals('Running migration \Schema\version_3', $logs[2]);
        $this->assertEquals('SQLSTATE[HY000]: General error: 1 near "TABL": syntax error', $logs[3]);
    }
}

<?php

use PHPUnit\Framework\TestCase;
use PicoDb\Database;

class SqliteLobTest extends TestCase
{
    private Database $db;

    public function setUp(): void
    {
        $this->db = new Database(['driver' => 'sqlite', 'filename' => ':memory:']);
        $this->db->getConnection()->exec('DROP TABLE IF EXISTS large_objects');
        $this->db->getConnection()->exec('CREATE TABLE large_objects (id VARCHAR(20), file_content BLOB)');
    }

    public function testInsert(): void
    {
        $result = $this->db->largeObject('large_objects')->insertFromFile('file_content', __FILE__, ['id' => 'test']);
        $this->assertTrue($result);
    }

    public function testInsertFromString(): void
    {
        $data = 'test';
        $result = $this->db->largeObject('large_objects')->insertFromString('file_content', $data, ['id' => 'test']);
        $this->assertTrue($result);
    }

    public function testInsertWithOptionalParams(): void
    {
        $result = $this->db->largeObject('large_objects')->insertFromFile('file_content', __FILE__);
        $this->assertTrue($result);
    }

    public function testFindOneColumnAsStream(): void
    {
        $result = $this->db->largeObject('large_objects')->insertFromFile('file_content', __FILE__, ['id' => 'test']);
        $this->assertTrue($result);

        $contents = $this->db->largeObject('large_objects')->eq('id', 'test')->findOneColumnAsStream('file_content');
        $this->assertSame(md5(file_get_contents(__FILE__)), md5(stream_get_contents($contents)));
    }

    public function testFindOneColumnAsString(): void
    {
        $result = $this->db->largeObject('large_objects')->insertFromFile('file_content', __FILE__, ['id' => 'test']);
        $this->assertTrue($result);

        $contents = $this->db->largeObject('large_objects')->eq('id', 'test')->findOneColumnAsString('file_content');
        $this->assertSame(md5(file_get_contents(__FILE__)), md5($contents));
    }

    public function testFindOneColumnAsStringReturnsEmptyStringWhenNoRowMatches(): void
    {
        $contents = $this->db->largeObject('large_objects')->eq('id', 'nonexistent')->findOneColumnAsString('file_content');
        $this->assertSame('', $contents);
    }

    public function testUpdate(): void
    {
        $result = $this->db->largeObject('large_objects')->insertFromFile('file_content', __FILE__, ['id' => 'test1']);
        $this->assertTrue($result);

        $result = $this->db->largeObject('large_objects')->insertFromFile('file_content', __FILE__, ['id' => 'test2']);
        $this->assertTrue($result);

        $result = $this->db->largeObject('large_objects')->eq('id', 'test1')->updateFromFile('file_content', __DIR__.'/../LICENSE');
        $this->assertTrue($result);

        $contents = $this->db->largeObject('large_objects')->eq('id', 'test1')->findOneColumnAsString('file_content');
        $this->assertSame(md5(file_get_contents(__DIR__.'/../LICENSE')), md5($contents));

        $contents = $this->db->largeObject('large_objects')->eq('id', 'test2')->findOneColumnAsString('file_content');
        $this->assertSame(md5(file_get_contents(__FILE__)), md5($contents));

        $result = $this->db->largeObject('large_objects')->updateFromFile('file_content', __DIR__.'/../composer.json');
        $this->assertTrue($result);

        $contents = $this->db->largeObject('large_objects')->eq('id', 'test1')->findOneColumnAsString('file_content');
        $this->assertSame(md5(file_get_contents(__DIR__.'/../composer.json')), md5($contents));

        $contents = $this->db->largeObject('large_objects')->eq('id', 'test2')->findOneColumnAsString('file_content');
        $this->assertSame(md5(file_get_contents(__DIR__.'/../composer.json')), md5($contents));
    }
}

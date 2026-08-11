<?php

declare(strict_types=1);

namespace Schema;

use PDO;

function version_1(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE test1 (column1 TEXT)');
}

function version_2(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE test2 (column2 TEXT)');
}

function version_3(PDO $pdo): void
{
    // Simulate an error
    $pdo->exec('CREATE TABL');
}

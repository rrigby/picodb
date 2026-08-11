<?php

declare(strict_types=1);

namespace AlternativeSchema;

use PDO;

function version_1(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE test1 (column1 TEXT)');
}

function version_2(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE test2 (column2 TEXT)');
}

PicoDb
======

PicoDb is a minimalist database query builder for PHP.

![Run Tests](https://github.com/rrigby/picodb/workflows/Run%20Tests/badge.svg)

Features
--------

- Easy to use, easy to hack, fast and very lightweight
- Supported drivers: Sqlite, Mssql, Mysql, Postgresql
- Requires only PDO
- Uses prepared statements, with values bound by type
- JSON column conditions
- Handles schema migrations
- Fully unit tested on PHP 8.3+
- License: MIT

Requirements
------------

- PHP >= 8.3
- PDO extension
- Sqlite, Mssql, Mysql or Postgresql

The test suite runs against MySQL 8.4 and 26.7, Postgres 15 and 18, and SQL Server 2022 and 2025.

Documentation
-------------

### Installation

```bash
composer require rrigby/picodb
```

### Database connection

#### Sqlite:

```php
use PicoDb\Database;

// Sqlite driver
$db = new Database(['driver' => 'sqlite', 'filename' => ':memory:']);
```

The Sqlite driver enables foreign keys by default.

Optional attributes:

- timeout

#### Microsoft SQL server:

```php
$db = new Database([
    'driver' => 'mssql',
    'hostname' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'my_db_name',
]);
```

Optional attributes:

- port
- schema_table (the default table name is "schema_version")
- trust_server_cert

#### Mysql:

```php
$db = new Database([
    'driver' => 'mysql',
    'hostname' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'my_db_name',
    'ssl_key' => '/path/to/client-key.pem',
    'ssl_cert' => '/path/to/client-cert.pem',
    'ssl_ca' => '/path/to/ca-cert.pem',
]);
```

Optional attributes:

- charset
- schema_table
- port
- ssl_key
- ssl_cert
- persistent
- timeout
- verify_server_cert
- case

#### Postgres:

```php
$db = new Database([
    'driver' => 'postgres',
    'hostname' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'my_db_name',
]);
```

Optional attributes:

- port
- schema_table
- timeout

### Execute any SQL query

```php
$db->execute('CREATE TABLE mytable (column1 TEXT)');
$db->execute('SELECT * FROM mytable WHERE column1 = ?', ['value']);
```

- Returns a `PDOStatement` if successful
- Throws a `SQLException` on any error, including duplicate keys. If a transaction is open, it is rolled back.

### Parameter binding

Values are bound with the PDO type that matches their PHP type:

| PHP value | Bound as        |
|-----------|-----------------|
| `null`    | `PDO::PARAM_NULL` |
| `bool`    | `PDO::PARAM_BOOL` |
| `int`     | `PDO::PARAM_INT`  |
| anything else | `PDO::PARAM_STR` |

On Postgres, booleans are sent as `t`/`f`, so they must target a `BOOLEAN` column or expression. Cast to `(int)` if the column is an integer.

### Insertion

```php
$db->table('mytable')->save(['column1' => 'test']);
```

or

```php
$db->table('mytable')->insert(['column1' => 'test']);
```

Insert and return the new primary key:

```php
$id = $db->table('mytable')->persist(['column1' => 'test']);
```

### Fetch last inserted id

```php
$db->getLastId();
```

### Transactions

```php
$db->transaction(function ($db) {
    $db->table('mytable')->save(['column1' => 'foo']);
    $db->table('mytable')->save(['column1' => 'bar']);
});
```

- Returns `true` if the callback returns null
- Returns the callback return value otherwise
- Throws an `SQLException` and rolls back if a query fails

or

```php
$db->startTransaction();
// Do something...
$db->closeTransaction();

// Rollback
$db->cancelTransaction();

// Check whether a transaction is open
$db->inTransaction();
```

### Fetch all data

```php
$records = $db->table('mytable')->findAll();

foreach ($records as $record) {
    var_dump($record['column1']);
}
```

Alter the result set with a callback:

```php
$db->table('mytable')->callback(fn (array $records) => array_column($records, 'column1'))->findAll();
```

### Updates

```php
$db->table('mytable')->eq('id', 1)->save(['column1' => 'hey']);
```

or

```php
$db->table('mytable')->eq('id', 1)->update(['column1' => 'hey']);
```

### Remove records

```php
$db->table('mytable')->lt('column1', 10)->remove();
```

Returns `true` if at least one row was deleted.

### Sorting

```php
$db->table('mytable')->asc('column1')->findAll();
```

or

```php
$db->table('mytable')->desc('column1')->findAll();
```

or

```php
$db->table('mytable')->orderBy('column1', 'ASC')->findAll();
```

Multiple sorting:

```php
$db->table('mytable')->asc('column1')->desc('column2')->findAll();
```

### Limit and offset

```php
$db->table('mytable')->limit(10)->offset(5)->findAll();
```

### Fetch only some columns

```php
$db->table('mytable')->columns('column1', 'column2')->findAll();
```

### Fetch only one column

Many rows:

```php
$db->table('mytable')->findAllByColumn('column1');
```

One row:

```php
$db->table('mytable')->findOneColumn('column1');
```

### Custom select

```php
$db->table('mytable')->select('1')->eq('id', 42)->findOne();
```

### Subquery as a column

```php
// SELECT "id", "title", (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) AS "comment_count" FROM "posts"
$db->table('posts')
   ->columns('id', 'title')
   ->subquery('SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id', 'comment_count')
   ->findAll();
```

### Distinct

```php
$db->table('mytable')->distinct('columnA')->findOne();
```

### Group by and having

```php
$db->table('mytable')->groupBy('columnA')->findAll();
```

Call `having()` to send the following conditions to the `HAVING` clause, and `where()` to switch back:

```php
$db->table('tags')
   ->columns('item_id')
   ->groupBy('item_id')
   ->having()
   ->whereRaw('COUNT(*) > ?', [1])
   ->findAll();
```

### Count

```php
$db->table('mytable')->count();
```

### Sum

```php
$db->table('mytable')->sum('columnB');
```

### Sum column values during update

Add the value 42 to the existing value of the column "mycolumn":

```php
$db->table('mytable')->sumColumn('mycolumn', 42)->update();
```

### Increment column

Increment a column value in a single query:

```php
$db->table('mytable')->eq('another_column', 42)->increment('my_column', 2);
```

### Decrement column

Decrement a column value in a single query:

```php
$db->table('mytable')->eq('another_column', 42)->decrement('my_column', 1);
```

### Exists

Returns true if a record exists otherwise false.

```php
$db->table('mytable')->eq('column1', 12)->exists();
```

### Joins

```php
// SELECT * FROM mytable LEFT JOIN my_other_table AS t1 ON t1.id=mytable.foreign_key
$db->table('mytable')->left('my_other_table', 't1', 'id', 'mytable', 'foreign_key')->findAll();
```

or

```php
// SELECT * FROM mytable LEFT JOIN my_other_table ON my_other_table.id=mytable.foreign_key
$db->table('mytable')->join('my_other_table', 'id', 'foreign_key')->findAll();
```

or

```php
// SELECT * FROM mytable INNER JOIN my_other_table AS t1 ON t1.id=mytable.foreign_key
$db->table('mytable')->inner('my_other_table', 't1', 'id', 'mytable', 'foreign_key')->findAll();
```

Additional equality conditions can be added to a left or inner join:

```php
// SELECT * FROM mytable LEFT JOIN my_other_table AS t1 ON t1.id=mytable.foreign_key and t1.status="active"
$db->table('mytable')->left('my_other_table', 't1', 'id', 'mytable', 'foreign_key', ['status' => 'active'])->findAll();
```

or

```php
// SELECT * FROM mytable LEFT JOIN my_other_table AS t1 ON t1.id=mytable.foreign_key and t1.status IN ("archived", "disabled")
$db->table('mytable')->left('my_other_table', 't1', 'id', 'mytable', 'foreign_key', ['status' => ['archived', 'disabled']])->findAll();
```

Join onto a subquery with `joinSubquery()` (LEFT JOIN) or `innerJoinSubquery()` (INNER JOIN):

```php
// SELECT * FROM mytable LEFT JOIN (SELECT ...) AS t1 ON t1.id=mytable.foreign_key
$subquery = $db->table('my_other_table')->columns('id', 'column2')->eq('status', 'active');

$db->table('mytable')->joinSubquery($subquery, 't1', 'id', 'foreign_key')->findAll();
```


### Equals condition

```php
$db->table('mytable')
   ->eq('column1', 'hey')
   ->findAll();
```

Not equals:

```php
$db->table('mytable')
   ->neq('column1', 'hey')
   ->findAll();
```

### IN condition

```php
$db->table('mytable')
   ->in('column1', ['hey', 'bla'])
   ->findAll();
```

Use `notIn()` for `NOT IN`.

### IN condition with subquery

```php
$subquery = $db->table('another_table')->columns('column2')->eq('column3', 'value3');

$db->table('mytable')
   ->columns('column_5')
   ->inSubquery('column1', $subquery)
   ->findAll();
```

Use `notInSubquery()` for `NOT IN`. Comparisons against a subquery are also available with `gtSubquery()`, `gteSubquery()`, `ltSubquery()` and `lteSubquery()`.

### Like condition

Case-sensitive (only Mysql and Postgres):

```php
$db->table('mytable')
   ->like('column1', '%Foo%')
   ->findAll();
```

Not case-sensitive:

```php
$db->table('mytable')
   ->ilike('column1', '%foo%')
   ->findAll();
```

Use `notLike()` for `NOT LIKE`.

### Less than condition

```php
$db->table('mytable')
   ->lt('column1', 2)
   ->findAll();
```

### Less than or equal condition

```php
$db->table('mytable')
   ->lte('column1', 2)
   ->findAll();
```

### Greater than condition

```php
$db->table('mytable')
   ->gt('column1', 3)
   ->findAll();
```

### Greater than or equal condition

```php
$db->table('mytable')
    ->gte('column1', 3)
    ->findAll();
```

### BETWEEN condition

```php
$db->table('mytable')
   ->between('column1', 1, 10)
   ->findAll();
```

Use `notBetween()` for `NOT BETWEEN`.

### IS NULL condition

```php
$db->table('mytable')
   ->isNull('column1')
   ->findAll();
```

### IS NOT NULL condition

```php
$db->table('mytable')
   ->notNull('column1')
   ->findAll();
```

### Raw conditions

Add a raw SQL condition with its own bound values. It's wrapped in parentheses and combined with the other conditions:

```php
$db->table('mytable')
   ->eq('column1', 'hey')
   ->whereRaw('column2 BETWEEN ? AND ?', [5, 15])
   ->findAll();
```

### JSON conditions

Query values inside JSON columns. Paths can be written as `key`, `key1.key2` or JSONPath (`$.key1.key2`).

```php
// Scalar value at a path
$db->table('mytable')->jsonEq('data', 'address.city', 'NYC')->findAll();
$db->table('mytable')->jsonNeq('data', 'user', 'alice')->findAll();

// The JSON array contains all the given values
$db->table('mytable')->jsonContains('tags', ['red', 'blue'])->findAll();
$db->table('mytable')->jsonContains('data', ['admin'], 'roles')->findAll();

// The inverse of jsonContains
$db->table('mytable')->jsonNotContains('tags', ['archived'])->findAll();
```

On Postgres, JSON columns must be `jsonb`.

### Multiple conditions

All conditions are joined by an `AND`.

```php
$db->table('mytable')
    ->like('column2', '%mytable')
    ->gte('column1', 3)
    ->findAll();
```

How to make an OR condition:

```php
$db->table('mytable')
    ->beginOr()
    ->like('column2', '%mytable')
    ->gte('column1', 3)
    ->closeOr()
    ->eq('column5', 'titi')
    ->findAll();
```

How to make an XOR condition (MySQL and SQL Server only):

```php
$db->table('mytable')
    ->beginXor()
    ->like('column2', '%mytable')
    ->gte('column1', 3)
    ->closeXor()
    ->eq('column5', 'titi')
    ->findAll();
```

How to make a NOT condition:

```php
$db->table('mytable')
    ->beginNot()
    ->like('column2', '%mytable')
    ->gte('column1', 3)
    ->closeNot()
    ->eq('column5', 'titi')
    ->findAll();
```

Logical conditions can be embedded within other logical conditions:

```php
$db->table('mytable')
    ->beginOr()
    ->like('column2', '%mytable')
    ->beginAnd()
    ->gte('column1', 3)
    ->eq('column5', 'titi')
    ->closeAnd()
    ->closeOr()
    ->findAll();
```

The same groups can be written with closures using `and()`, `or()`, `not()` and `xor()`:

```php
$db->table('mytable')
    ->or(fn ($query) => $query
        ->like('column2', '%mytable')
        ->and(fn ($query) => $query
            ->gte('column1', 3)
            ->eq('column5', 'titi')
        )
    )
    ->findAll();
```

`not()` joins its conditions with `AND`. To negate an `OR` group, nest it: `->not(fn ($q) => $q->or(...))`.

### Conditional clauses

Apply conditions only when a value is set, with an optional fallback:

```php
$db->table('mytable')
    ->when($status !== null, fn ($query) => $query->eq('status', $status))
    ->when($sort === 'newest', fn ($query) => $query->desc('created_at'), fn ($query) => $query->asc('created_at'))
    ->findAll();
```


### Debugging

Log generated queries:

```php
$db->getStatementHandler()->withLogging();

// Include the bound values in the log
$db->getStatementHandler()->withLogging(true);
```

Measure each query time:

```php
$db->getStatementHandler()->withStopWatch();
```

Log the `EXPLAIN` output of each query:

```php
$db->getStatementHandler()->withExplain();
```

Get the number of queries executed:

```php
echo $db->getStatementHandler()->getNbQueries();
```

Get log messages:

```php
print_r($db->getLogMessages());
```

### Large objects (LOBs)

Insert a file:

```php
$db->largeObject('my_table')->insertFromFile('blobColumn', '/path/to/file', ['id' => 'something']);
```

Insert from a stream:

```php
$db->largeObject('my_table')->insertFromStream('blobColumn', $fd, ['id' => 'something']);
```

Insert from a string:

```php
$db->largeObject('my_table')->insertFromString('blobColumn', $data, ['id' => 'something']);
```

Update from a file or a stream:

```php
$db->largeObject('my_table')->eq('id', 'something')->updateFromFile('blobColumn', '/path/to/file');
$db->largeObject('my_table')->eq('id', 'something')->updateFromStream('blobColumn', $fd);
```

Fetch a large object as a stream (Postgres only):

```php
$fd = $db->largeObject('my_table')->eq('id', 'something')->findOneColumnAsStream('blobColumn');
```

Fetch a large object as a string:

```php
echo $db->largeObject('my_table')->eq('id', 'something')->findOneColumnAsString('blobColumn');
```

Drivers:

- Postgres
    - Column type: `bytea`
- Sqlite and Mysql
    - Column type: `BLOB`
    - PDO does not support streams (returns a string instead)

### Hashtable (key/value store)

How to use a table as a key/value store:

```php
$db->execute(
     'CREATE TABLE mytable (
         column1 TEXT NOT NULL UNIQUE,
         column2 TEXT default NULL
     )'
);

$db->table('mytable')->insert(['column1' => 'option1', 'column2' => 'value1']);
```

Add/Replace some values:

```php
$db->hashtable('mytable')
   ->columnKey('column1')
   ->columnValue('column2')
   ->put(['option1' => 'new value', 'option2' => 'value2']);
```

Get all values:

```php
$result = $db->hashtable('mytable')->columnKey('column1')->columnValue('column2')->get();
print_r($result);

Array
(
    [option2] => value2
    [option1] => new value
)
```

or

```php
$result = $db->hashtable('mytable')->getAll('column1', 'column2');
```

Get a specific value:

```php
$db->hashtable('mytable')
   ->columnKey('column1')
   ->columnValue('column2')
   ->put(['option3' => 'value3']);

$result = $db->hashtable('mytable')
             ->columnKey('column1')
             ->columnValue('column2')
             ->get('option1', 'option3');

print_r($result);

Array
(
    [option1] => new value
    [option3] => value3
)
```

### Schema migrations

#### Define a migration

- Migrations are defined in simple functions inside a namespace named "Schema".
- An instance of PDO is passed as the first argument of the function.
- Function names have the version number at the end.

Example:

```php
namespace Schema;

function version_1($pdo)
{
    $pdo->exec('
        CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            name TEXT UNIQUE,
            email TEXT UNIQUE,
            password TEXT
        )
    ');
}


function version_2($pdo)
{
    $pdo->exec('
        CREATE TABLE tags (
            id INTEGER PRIMARY KEY,
            name TEXT UNIQUE
        )
    ');
}
```

#### Run schema update automatically

- The method `check()` executes all migrations up to the version specified
- If an error occurs, the transaction is rolled back
- Foreign key checks are disabled if possible during the migration

Example:

```php
$last_schema_version = 5;

$db = new PicoDb\Database([
    'driver' => 'sqlite',
    'filename' => '/tmp/mydb.sqlite',
]);

if (! $db->schema()->check($last_schema_version)) {
    die('Unable to migrate database schema.');
}
```

Use a different namespace for the migration functions:

```php
$db->schema('App\\Migrations')->check($last_schema_version);
```

### Use a singleton to handle database instances

Register an instance. The callback runs the first time the instance is requested:

```php
PicoDb\Database::setInstance('myinstance', function () {
    $db = new PicoDb\Database([
        'driver' => 'sqlite',
        'filename' => DB_FILENAME,
    ]);

    if (! $db->schema()->check(DB_VERSION)) {
        die('Unable to migrate database schema.');
    }

    return $db;
});
```

Get this instance anywhere in your code:

```php
PicoDb\Database::getInstance('myinstance')->table(...);
```

`getInstance()` throws a `LogicException` if no instance was registered with that name.

Development
-----------

Start the database containers and run the test suite against every driver:

```bash
composer docker:start
composer test
composer docker:stop
```

Static analysis and code style:

```bash
composer phpstan
composer rector      # dry run, use rector:fix to apply
composer cs          # dry run, use cs:fix to apply
```

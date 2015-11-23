Addiks PHP-SQL Database
===================================

[![Build Status](https://travis-ci.org/addiks/phpsql.svg?branch=master)](https://travis-ci.org/addiks/phpsql)

This projects's goal is to create an SQL-compliant database completely written in PHP.
It's purposes are wide ranged:

 - It can be used to mock a temporary in-memory database for unit-tests.
 - The SQL-Parser can be used to extract valuable information from external SQL-queries.
 - This database does not depend on php-modules or a seperate server, it just works.
 - The obligatory learning-by-doing purpose
 
```
 !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 !!!                                                        !!!
 !!!                         WARNING                        !!!
 !!!                                                        !!!
 !!!   DO NOT USE THIS DATABASE IN PRODUCTION ENVIRONMENT!  !!!
 !!!                                                        !!!
 !!!            If you do disregard this warning,           !!!
 !!! you alone are responsible for any damage or data-loss! !!!
 !!!                                                        !!!
 !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
```

I currently use phpsql only as an in-memory database. While in theory it can also work on the filesystem (for in-memory databases, it mocks an in-memory filesystem), that is currently mostly untested.

## Installation:

There are several ways to install PHPSQL. The recommended way of installation is using composer.

# Using composer:

Add the following to the **require**-area your **composer.json** require-area:

```json
    "require": {
        "addiks/phpsql": "^0.1.0"
    },
```

Then [install or update your requirements using composer](https://getcomposer.org/doc/01-basic-usage.md#installing-dependencies)

# Git:

 - Clone the URL **https://github.com/addiks/phpsql.git** into any directory.
 - Include the file **bin/bootstrap.php** in PHP.

# Archive download

 - Download the URL **https://github.com/addiks/phpsql/archive/master.zip** into any directory.
 - Include the file **bin/bootstrap.php** in PHP.

## How to use:

PHPSQL provides an PDO replacement, which should be used to instanciate the database.

This PDO replacement can be used just like [PHP's PDO class](http://php.net/pdo).

```php
<?php

use Addiks\PHPSQL\PDO\PDO;
use Addiks\PHPSQL\Result\ResultWriter;

# create a new database in memory
$pdo = new PDO('inmemory:some_example_database');

$pdo->query("
    CREATE TABLE foo(
        id INT PRIMARY KEY AUTO_INCREMENT,
        bar VARCHAR(32),
        baz DECIMAL(3,12)
    )
");

$pdo->query("
    INSERT INTO foo
        (bar, baz)
    VALUES
        (?, ?)
", ['Lorem ipsum', 3.1415]);

$result = $pdo->query("SELECT * FROM foo");

$rows = $result->fetchAll(PDO::FETCH_NUM);

foreach ($rows as $row) {
    var_dump($row);
}

# Creates an ASCII-art-table representing a result.
echo new ResultWriter($pdo->query('DESCRIBE foo')->getResult());

```

## Benchmarks:

There is a simple benchmarking script in 'bin/benchmark.php', which can compare execution-speed between mysql and phpsql.
Currently phpsql has no caches or other performance-improving measures built into it, which means that currently it is very slow.
On my machine phpsql (with PHP-5) is only about 30% as fast as mysql at insert's and select's are the same speed as insert's:

On mylsq (InnoDB):
```
 - opened database 'mysql:host=127.0.0.1;dbname=benchmark'.
 - inserting 10000 rows took 19.008 seconds.
 - selecting 10000 rows took 0.657 seconds.
```
 
On PHP-5.5:
```
 - opened database 'inmemory:benchmark'.
 - inserting 10000 rows took 67.782 seconds.
 - selecting 10000 rows took 65.639 seconds.
```

On PHP-7:
```
 - opened database 'inmemory:benchmark'.
 - inserting 10000 rows took 8.108 seconds.
 - selecting 10000 rows took 7.716 seconds.
```

Again: At it's current state, phpsql is NOT usable for any productive environment.


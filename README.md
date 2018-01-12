Addiks PHP-SQL Database
===================================

[![Build Status](https://travis-ci.org/addiks/phpsql.svg?branch=master)](https://travis-ci.org/addiks/phpsql)

This projects's goal is to create an SQL-compliant database completely written in PHP.
It's purposes are wide ranged:

 - It can be used to mock a temporary in-memory database for unit-tests.
 - A developer can debug directly from the application into the database to see why the db behaves like it does.
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

# Installation:

There are several ways to install PHPSQL. The recommended way of installation is using composer.

## Using composer:

Add the following to the **require**-area your **composer.json** require-area:

```json
    "require": {
        "addiks/phpsql": "^0.1.0"
    },
```

Then [install or update your requirements using composer](https://getcomposer.org/doc/01-basic-usage.md#installing-dependencies)

## Git:

 - Clone the URL **https://github.com/addiks/phpsql.git** into any directory.
 - Include the file **bin/bootstrap.php** in PHP.

## Archive download

 - Download the URL **https://github.com/addiks/phpsql/archive/master.zip** into any directory.
 - Include the file **bin/bootstrap.php** in PHP.

# How to use:

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

# Benchmarks:

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

As you can see, phpsql benefit's extremely from using php-7 (Kudos to the PHP-dev's!).

Again: At it's current state, phpsql is NOT usable for any productive environment.

# Versions / Releases

The releases are numbered like 'v*X*.*Y*.*Z*' and git-tags are used to manage releases.

 - X refers to an implementation.
    0: Pre-released unstable developer version.
    1: Stable release

 - Y refers to an set of features/API.
    Any version increase means changed/added features and/or changed API.
    Third-party components might become incompatible.

 - Z refers to the bugfix-version.
    The features and API did not change, only bugs in existing features were squashed.
    Third-party components will not become incompatible.
      (Except they relied on a bug to exist which hints to the developer of that component to be an idiot)

# Collaboration

See the file **HACKING.md** for instructions you need to be aware of when making changes to the code-base of this project.

For any change on phpsql, there should be an [issue](https://github.com/addiks/phpsql/issues/new) on github.
Branches related to issues should be named like 'issue#1234', where 1234 is the number of that issue.

There are many ways to contribute to this project:

 - Create issues for existing bug's or missing features. (Provide information on what you expected to happen and what instead happened.)
 
 - Implement feature that are yet missing. (Remember to open an issue before making changes to let others know you are currently implementing it)
 
 - Write tests. (Tests for yet missing features or failing tests should be committed in a different branch. Remeber to create an issue for that.)

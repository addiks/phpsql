
# Code Style

All code submitted to phpsql must comply with the [PSR-2 standard](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md) to be accepted.
You can use [php-codesniffer](https://github.com/squizlabs/PHP_CodeSniffer) to test if that is the case.
Furthermore you are advised to orientate your coding-style for phpsql on the already existing phpsql-code.

# Branches / Issues

For any change on phpsql, there should be an [issue](https://github.com/addiks/phpsql/issues/new) on github.
Branches related to issues should be named like 'issue#1234', where 1234 is the number of that issue.

# Tests

There are several ways to test your changes. Before pushing any code-changes to phpsql, you should at least make sure they do not fail the automated tests.

## Automated tests / PHPUnit

Before pushing any code-change to phpsql, you *must* execute the automated tests using [phpunit](https://phpunit.de/getting-started.html).
Simply executing phpunit without any parameters should execute all automated tests in a few seconds (depending on your machine).
You are advised to execute the automated tests before you begin coding to get a feel on how well they execute on your machine.
If they take a substancial longer time to execute on the patched version, you should investigate if your changes cause any slowdowns.

**Only patches that pass all automated tests will be accepted!**

There are three types of automated tests in phpsql: Behaviour-tests, Integration-tests and Unit-tests.

 - **Behaviour-tests** are tests which test the whole of phpsql as one big blackbox which can execute SQL statements.
   They tests end-user functions on an in-memory database by querying SQL-statements on an PDO-object.
   Example: Test if SELECT works by creating a table with CREATE-TABLE, INSERT a few data-sets and SELECT them to see if they match the inserted data.

 - **Integration-tests** are tests which test a bundle of related components together.
   Example: Set up a structure of Table-objects, ColumnData-objects and Indexes and see if they together correctly store and retrieve rows.

 - **Unit-tests** are tests which test one single component for correct behaviour.
   All depencies to other components are fulfilled by [mocking these components](https://phpunit.de/manual/current/en/test-doubles.html).
   (Multiple classes together can be seen as one component if they directly depend on each other like BTree and BTreeNode.)
   Example: See if the DELETE-statement executor tries to delete the correct rows on the correct tables/indexes by mocking these components.

## Benchmarking your changes

Before pushing any logical and/or big changes, you are advised to run the **bin/benchmark.php** script on the unchanged version and on the patched version to test if your patch cause any severe slowdowns.

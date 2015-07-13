Addiks PHP-SQL Database
===================================

This projects's goal is to create an SQL-compliant database completely written in PHP.
It's purposes are wide ranged:
 - It can be used to mock an temporary in-memory database for unit-tests.
 - The SQL-Parser can be used to extract valuable information from extenral SQL-queries.
 - This database does not depend on php-modules or a seperate server, it just works.
 - The obligatory learning-by-doing purpose

(To be honest, everyone knew it was only a matter of time until some maniac
 writes a full SQL-database in PHP, and well... here it is.)

## Features

 - SELECT stuff:
    - WHERE
    - ORDER BY
    - UNION
    - GROUP BY
    - HAVING
 - UPDATE; DELETE; INSERT
 - CREATE DATABASES
    - in memory
    - in files (binary)
 - CREATE TABLE:
    - indexes: b-tree, hash, insertion-sort, ...
    - foreign keys
 - ALTER TABLE
 - Parse SQL-queries into statement-objects

<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\BehaviorTests;

use PHPUnit_Framework_TestCase;
use Addiks\PHPSQL\PDO\PDO;

class TransactionsTest extends PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        $this->pdo = new PDO("inmemory:phpunit");

        $this->pdo->query("
            CREATE TABLE `phpunit_transaction_test` (
                id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
                foo INT,
                bar VARCHAR(32),
                baz DATETIME
            )
        ");

        $this->pdo->query("
            INSERT INTO `phpunit_transaction_test`
                (foo, bar, baz)
            VALUES
                (123, 'Lorem ipsum', '2001-02-03 04:05:06'),
                (456, 'dolor sit amet', '2007-08-09 10:11:12'),
                (789, 'consetetur sadipscing', '2013-04-15 16:17:18')
        ");

    }

    /**
     * @group behaviour.transactional.create_database
     * @dataProvider dataProviderCreateDatabase
     */
    public function testCreateDatabase($createDatabaseName, $doCommit, array $expectedDatabaseNames)
    {

        $this->pdo->query("START TRANSACTION");

        $this->pdo->query("CREATE DATABASE {$createDatabaseName}");

        if ($doCommit) {
            $this->pdo->query("COMMIT");
        } else {
            $this->pdo->query("ROLLBACK");
        }

        $result = $this->pdo->query("SHOW DATABASES");

        $actualDatabaseNames = array();
        foreach ($result->fetchAll() as list($databaseName)) {
            $actualDatabaseNames[] = $databaseName;
        }

        $this->assertEquals($expectedDatabaseNames, $actualDatabaseNames);
    }

    public function dataProviderCreateDatabase()
    {
        return array(
            [
                'someDatabase',
                true,
                ['default', 'information_schema', 'someDatabase'],
            ],
            [
                'someDatabase',
                false,
                ['default', 'information_schema'],
            ],
        );
    }

    /**
     * @group behaviour.transactional.create_table
     * @dataProvider dataProviderCreateTable
     */
    public function testCreateTable($createTableName, $doCommit, array $expectedTableNames)
    {
        $this->pdo->query("START TRANSACTION");

        $this->pdo->query("
            CREATE TABLE {$createTableName} (
                id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
                blah TINYINT,
                blub BLOB
            )
        ");

        if ($doCommit) {
            $this->pdo->query("COMMIT");
        } else {
            $this->pdo->query("ROLLBACK");
        }

        $result = $this->pdo->query("SHOW TABLES");

        $actualTableNames = array();
        foreach ($result->fetchAll() as list($tableName)) {
            $actualTableNames[] = $tableName;
        }

        $this->assertEquals($expectedTableNames, $actualTableNames);
    }

    public function dataProviderCreateTable()
    {
        return array(
            [
                'someTable',
                true,
                ['phpunit_transaction_test', 'someTable'],
            ],
            [
                'otherTable',
                false,
                ['phpunit_transaction_test'],
            ],
        );
    }

    /**
     * @group behaviour.transactional.alter.add_column
     * @dataProvider dataProviderAlterTableAddColumn
     */
    public function testAlterTableAddColumn($addColumnName, $addColumnType, $doCommit, $expectedColumns)
    {
        $this->pdo->query("START TRANSACTION");

        $this->pdo->query("ALTER TABLE `phpunit_transaction_test` ADD COLUMN {$addColumnName} {$addColumnType}");

        if ($doCommit) {
            $this->pdo->query("COMMIT");
        } else {
            $this->pdo->query("ROLLBACK");
        }

        $this->assertEquals($expectedColumns, $this->getColumnList());
    }

    public function dataProviderAlterTableAddColumn()
    {
        return array(
            [
                'faz',
                'TINYINT(1)',
                true,
                [
                    'bar' => ['VARCHAR'],
                    'baz' => ['DATETIME'],
                    'faz' => ['TINYINT'],
                    'foo' => ['INT'],
                    'id'  => ['INT'],
                ]
            ],
            [
                'faz',
                'TINYINT(1)',
                false,
                [
                    'bar' => ['VARCHAR'],
                    'baz' => ['DATETIME'],
                    'foo' => ['INT'],
                    'id'  => ['INT'],
                ]
            ],
        );
    }

    /**
     * @group behaviour.transactional.alter.drop_column
     * @dataProvider dataProviderAlterTableDropColumn
     */
    public function testAlterTableDropColumn($dropColumnName, $doCommit, $expectedColumns)
    {
        $this->pdo->query("START TRANSACTION");

        $this->pdo->query("ALTER TABLE `phpunit_transaction_test` DROP COLUMN {$dropColumnName}");

        if ($doCommit) {
            $this->pdo->query("COMMIT");
        } else {
            $this->pdo->query("ROLLBACK");
        }

        $this->assertEquals($expectedColumns, $this->getColumnList());
    }

    public function dataProviderAlterTableDropColumn()
    {
        return array(
            [
                'foo',
                true,
                [
                    'bar' => ['VARCHAR'],
                    'baz' => ['DATETIME'],
                    'id'  => ['INT'],
                ]
            ],
            [
                'foo',
                false,
                [
                    'bar' => ['VARCHAR'],
                    'baz' => ['DATETIME'],
                    'foo' => ['INT'],
                    'id'  => ['INT'],
                ]
            ],
            [
                'bar',
                true,
                [
                    'baz' => ['DATETIME'],
                    'foo' => ['INT'],
                    'id'  => ['INT'],
                ]
            ],
            [
                'bar',
                false,
                [
                    'bar' => ['VARCHAR'],
                    'baz' => ['DATETIME'],
                    'foo' => ['INT'],
                    'id'  => ['INT'],
                ]
            ],
            [
                'baz',
                true,
                [
                    'bar' => ['VARCHAR'],
                    'foo' => ['INT'],
                    'id'  => ['INT'],
                ]
            ],
            [
                'baz',
                false,
                [
                    'bar' => ['VARCHAR'],
                    'baz' => ['DATETIME'],
                    'foo' => ['INT'],
                    'id'  => ['INT'],
                ]
            ],
        );
    }

    /**
     * @group behaviour.transactional.alter.modify_column
     * @dataProvider dataProviderAlterTableModifyColumn
     */
    public function testAlterTableModifyColumn($modifyColumnName, $modifyColumnType, $doCommit, $expectedColumns)
    {
        $this->pdo->query("START TRANSACTION");

        $this->pdo->query("ALTER TABLE `phpunit_transaction_test` MODIFY COLUMN {$modifyColumnName} {$modifyColumnType}");

        if ($doCommit) {
            $this->pdo->query("COMMIT");
        } else {
            $this->pdo->query("ROLLBACK");
        }

        $this->assertEquals($expectedColumns, $this->getColumnList());
    }

    public function dataProviderAlterTableModifyColumn()
    {
        return array(
            [
                'foo',
                'VARCHAR(16)',
                true,
                [
                    'bar' => ['VARCHAR'],
                    'baz' => ['DATETIME'],
                    'foo' => ['VARCHAR'],
                    'id'  => ['INT'],
                ]
            ],
            [
                'foo',
                'VARCHAR(16)',
                false,
                [
                    'bar' => ['VARCHAR'],
                    'baz' => ['DATETIME'],
                    'foo' => ['INT'],
                    'id'  => ['INT'],
                ]
            ],
        );
    }

    /**
     * @group behaviour.transactional.insert_into
     * @dataProvider dataProviderInsertInto
     */
    public function testInsertInto(array $insertRows, $doCommit, $expectedRows)
    {
        $this->pdo->query("START TRANSACTION");

        foreach ($insertRows as $insertRow) {
            $this->pdo->query("
                INSERT INTO `phpunit_transaction_test`
                    (foo, bar, baz)
                VALUES
                    (?, ?, ?)
                ",
                $insertRow
            );
        }

        if ($doCommit) {
            $this->pdo->query("COMMIT");
        } else {
            $this->pdo->query("ROLLBACK");
        }

        $result = $this->pdo->query("SELECT id, foo, bar, baz FROM `phpunit_transaction_test`");
        $actualRows = $result->fetchAll(PDO::FETCH_NUM);
        $this->assertEquals($expectedRows, $actualRows);
    }

    public function dataProviderInsertInto()
    {
        return array(
            [
                [
                    [135, 'elitr',    '1989-11-09 12:00:00'],
                    [246, 'sed diam', '1990-03-18 11:00:00'],
                ],
                true,
                [
                    [1, 123, 'Lorem ipsum',           '2001-02-03 04:05:06'],
                    [2, 456, 'dolor sit amet',        '2007-08-09 10:11:12'],
                    [3, 789, 'consetetur sadipscing', '2013-04-15 16:17:18'],
                    [4, 135, 'elitr',                 '1989-11-09 12:00:00'],
                    [5, 246, 'sed diam',              '1990-03-18 11:00:00'],
                ],
            ],
            [
                [
                    [135, 'elitr',    '1989-11-09 12:00:00'],
                    [246, 'sed diam', '1990-03-18 11:00:00'],
                ],
                false,
                [
                    [1, 123, 'Lorem ipsum',           '2001-02-03 04:05:06'],
                    [2, 456, 'dolor sit amet',        '2007-08-09 10:11:12'],
                    [3, 789, 'consetetur sadipscing', '2013-04-15 16:17:18'],
                ],
            ],
        );
    }

    /**
     * @group behaviour.transactional.update
     * @dataProvider dataProviderUpdate
     */
    public function testUpdate(array $updates, $doCommit, $expectedRows)
    {
        $this->pdo->query("START TRANSACTION");

        foreach ($updates as $foo => $bar) {
            $this->pdo->query("
                UPDATE `phpunit_transaction_test`
                SET bar = ?
                WHERE foo = ?
            ", [$bar, $foo]);
        }

        if ($doCommit) {
            $this->pdo->query("COMMIT");
        } else {
            $this->pdo->query("ROLLBACK");
        }

        $result = $this->pdo->query("SELECT id, foo, bar, baz FROM `phpunit_transaction_test`");
        $actualRows = $result->fetchAll(PDO::FETCH_NUM);
        $this->assertEquals($expectedRows, $actualRows);
    }

    public function dataProviderUpdate()
    {
        return array(
            [
                [
                    '123' => 'Stet clita',
                    '789' => 'justo duo',
                ],
                true,
                [
                    ["1", "123", 'Stet clita',     '2001-02-03 04:05:06'],
                    ["2", "456", 'dolor sit amet', '2007-08-09 10:11:12'],
                    ["3", "789", 'justo duo',      '2013-04-15 16:17:18'],
                ],
            ],
            [
                [
                    '123' => 'Stet clita',
                    '789' => 'justo duo',
                ],
                false,
                [
                    ["1", "123", 'Lorem ipsum',           '2001-02-03 04:05:06'],
                    ["2", "456", 'dolor sit amet',        '2007-08-09 10:11:12'],
                    ["3", "789", 'consetetur sadipscing', '2013-04-15 16:17:18'],
                ],
            ],
        );
    }

    ### HELPERS

    protected function getColumnList()
    {
        $result = $this->pdo->query("
            SELECT COLUMN_NAME, DATA_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_NAME = 'phpunit_transaction_test'
        ");

        $actualColumns = array();

        foreach ($result->fetchAll() as list($columnName, $columnType)) {
            $actualColumns[$columnName] = [strtoupper($columnType)];
        }

        ksort($actualColumns);

        return $actualColumns;
    }

}

<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\BehaviourTests;

use Addiks\PHPSQL\PDO\PDO;
use PHPUnit_Framework_TestCase;

class InformationSchemaTest extends PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        $this->pdo = new PDO("inmemory:phpunit");

        $this->pdo->query("CREATE DATABASE `phpunit_informationschema`");

        $this->pdo->query("USE `phpunit_informationschema`");

        $this->pdo->query("
            CREATE TABLE `phpunit_informationschema_test` (
                id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
                foo INT,
                bar VARCHAR(32) DEFAULT 'Lorem',
                baz DATETIME NOT NULL
            )
        ");

/*
        $this->pdo->query("
            CREATE VIEW 
                `phpunit_informationschema_view`
            AS SELECT
                id,
                foo * 2 as `foofoo`
            FROM
                `phpunit_informationschema_test`
        ");
*/
    }

    /**
     * @group behaviour.information_schema
     */
    public function testTablesExisting()
    {
        $this->assertEquals([
            ['COLUMNS'],
            ['ENGINES'],
            ['SCHEMATA'],
            ['TABLES'],
            ['VIEWS'],
        ], $this->pdo->query("SHOW TABLES from information_schema")->fetchAll(PDO::FETCH_NUM));
    }

    /**
     * @dep ends testTablesExisting
     * @group behaviour.information_schema
     * @group behaviour.information_schema.columns
     */
    public function testColumnsHasRightColumns()
    {
        $result = $this->pdo->query("DESCRIBE information_schema.COLUMNS");
        $actualRows = $result->fetchAll(PDO::FETCH_NUM);
        $actualHeaders = array_column($actualRows, 0);

        $expectedHeaders = [
            'TABLE_CATALOG',
            'TABLE_SCHEMA',
            'TABLE_NAME',
            'COLUMN_NAME',
            'ORDINAL_POSITION',
            'COLUMN_DEFAULT',
            'IS_NULLABLE',
            'DATA_TYPE',
            'CHARACTER_MAXIMUM_LENGTH',
            'CHARACTER_OCTET_LENGTH',
            'NUMERIC_PRECISION',
            'NUMERIC_SCALE',
            'DATETIME_PRECISION',
            'CHARACTER_SET_NAME',
            'COLLATION_NAME',
            'COLUMN_TYPE',
            'COLUMN_KEY',
            'EXTRA',
            'PRIVILEGES',
            'COLUMN_COMMENT',
        ];

        $this->assertEquals(
            $expectedHeaders,
            $actualHeaders
        );
    }

    /**
     * @depends testTablesExisting
     * @group behaviour.information_schema
     * @group behaviour.information_schema.engines
     */
    public function testEnginesHasRightColumns()
    {
        $result = $this->pdo->query("DESCRIBE information_schema.ENGINES");
        $actualRows = $result->fetchAll(PDO::FETCH_NUM);
        $actualHeaders = array_column($actualRows, 0);

        $expectedHeaders = [
            'ENGINE',
            'SUPPORT',
            'COMMENT',
            'TRANSACTIONS',
            'XA',
            'SAVEPOINTS',
        ];

        $this->assertEquals(
            $expectedHeaders,
            $actualHeaders
        );
    }

    /**
     * @depends testTablesExisting
     * @group behaviour.information_schema
     * @group behaviour.information_schema.schemata
     */
    public function testSchemataHasRightColumns()
    {
        $result = $this->pdo->query("DESCRIBE information_schema.SCHEMATA");
        $actualRows = $result->fetchAll(PDO::FETCH_NUM);
        $actualHeaders = array_column($actualRows, 0);

        $expectedHeaders = [
            'CATALOG_NAME',
            'SCHEMA_NAME',
            'DEFAULT_CHARACTER_SET_NAME',
            'DEFAULT_COLLATION_NAME',
            'SQL_PATH',
        ];

        $this->assertEquals(
            $expectedHeaders,
            $actualHeaders
        );
    }

    /**
     * @depends testTablesExisting
     * @group behaviour.information_schema
     * @group behaviour.information_schema.table
     */
    public function testTablesHasRightColumns()
    {
        $result = $this->pdo->query("DESCRIBE information_schema.TABLES");
        $actualRows = $result->fetchAll(PDO::FETCH_NUM);
        $actualHeaders = array_column($actualRows, 0);

        $expectedHeaders = [
            'TABLE_CATALOG',
            'TABLE_SCHEMA',
            'TABLE_NAME',
            'TABLE_TYPE',
            'ENGINE',
            'VERSION',
            'ROW_FORMAT',
            'TABLE_ROWS',
            'AVG_ROW_LENGTH',
            'DATA_LENGTH',
            'MAX_DATA_LENGTH',
            'INDEX_LENGTH',
            'DATA_FREE',
            'AUTO_INCREMENT',
            'CREATE_TIME',
            'UPDATE_TIME',
            'CHECK_TIME',
            'TABLE_COLLATION',
            'CHECKSUM',
            'CREATE_OPTIONS',
            'TABLE_COMMENT',
        ];

        $this->assertEquals(
            $expectedHeaders,
            $actualHeaders
        );
    }

    /**
     * @depends testTablesExisting
     * @group behaviour.information_schema
     * @group behaviour.information_schema.view
     */
    public function testViewsHasRightColumns()
    {
        $result = $this->pdo->query("DESCRIBE information_schema.VIEWS");
        $actualRows = $result->fetchAll(PDO::FETCH_NUM);
        $actualHeaders = array_column($actualRows, 0);

        $expectedHeaders = [
            'TABLE_CATALOG',
            'TABLE_SCHEMA',
            'TABLE_NAME',
            'VIEW_DEFINITION',
            'CHECK_OPTION',
            'IS_UPDATABLE',
            'DEFINER',
            'SECURITY_TYPE',
            'CHARACTER_SET_CLIENT',
            'COLLATION_CONNECTION',
        ];

        $this->assertEquals(
            $expectedHeaders,
            $actualHeaders
        );
    }

    /**
     * @depends testSchemataHasRightColumns
     * @group behaviour.information_schema
     * @group behaviour.information_schema.database
     */
    public function testCreatedDatabaseExists()
    {
        $result = $this->pdo->query("
            SELECT
                SCHEMA_NAME
            FROM
                information_schema.SCHEMATA
        ");

        $this->assertContains(["phpunit_informationschema"], $result->fetchAll(PDO::FETCH_NUM));
    }

    /**
     * @depends testTablesHasRightColumns
     * @group behaviour.information_schema
     * @group behaviour.information_schema.table
     */
    public function testCreatedTableInTables()
    {
        $result = $this->pdo->query("
            SELECT
                TABLE_NAME,
                ENGINE
            FROM
                information_schema.TABLES
            WHERE
                TABLE_SCHEMA = 'phpunit_informationschema'
        ");

        $this->assertEquals([
            ['phpunit_informationschema_test', 'InnoDB'],
        ], $result->fetchAll(PDO::FETCH_NUM));

        $result = $this->pdo->query("
            SELECT
                TABLE_NAME,
                COLUMN_NAME,
                ORDINAL_POSITION,
                COLUMN_DEFAULT,
                IS_NULLABLE,
                DATA_TYPE
            FROM
                information_schema.COLUMNS
            WHERE
                TABLE_SCHEMA = 'phpunit_informationschema'
        ");

        $this->assertEquals([
            ['phpunit_informationschema_test', 'id',  '0', NULL,    '0', 'INTEGER'],
            ['phpunit_informationschema_test', 'foo', '1', NULL,    '1', 'INTEGER'],
            ['phpunit_informationschema_test', 'bar', '2', 'Lorem', '1', 'VARCHAR'],
            ['phpunit_informationschema_test', 'baz', '3', NULL,    '0', 'DATETIME'],
        ], $result->fetchAll(PDO::FETCH_NUM));
    }

    /**
     * @depends testTablesExisting
     * @group behaviour.information_schema
     * @group behaviour.information_schema.view
     */
    public function testCreatedViewInTables()
    {
        $this->markTestSkipped();

        $result = $this->pdo->query("
            SELECT
                TABLE_NAME,
                VIEW_DEFINITION
            FROM
                information_schema.VIEWS
            WHERE
                TABLE_SCHEMA = 'phpunit_informationschema'
        ");

        $expectedSql = "SELECT
                id,
                foo * 2 as `foofoo`
            FROM
                `phpunit_informationschema_test`";

        $this->assertEquals([
            ['phpunit_informationschema_view', $expectedSql],
        ], $result->fetchAll(PDO::FETCH_NUM));
    }

}

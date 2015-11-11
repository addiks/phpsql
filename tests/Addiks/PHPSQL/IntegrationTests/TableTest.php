<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\IntegrationTests;

use PHPUnit_Framework_TestCase;
use Addiks\PHPSQL\Table\Table;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;
use Addiks\PHPSQL\Column\ColumnData;
use Addiks\PHPSQL\Column\ColumnSchema;
use Addiks\PHPSQL\Value\Enum\Page\Column\DataType;
use Addiks\PHPSQL\Index\BTree;
use Addiks\PHPSQL\Table\TableSchema;
use Addiks\PHPSQL\Index\IndexSchema;
use Addiks\PHPSQL\Value\Enum\Page\Index\IndexEngine;
use Addiks\PHPSQL\Value\Enum\Page\Index\Type;

class TableTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $columnSchemaA = new ColumnSchema();
        $columnSchemaA->setName("foo");
        $columnSchemaA->setDataType(DataType::INTEGER());
        $columnSchemaA->setLength(4);
        $columnSchemaA->setExtraFlags(ColumnSchema::EXTRA_PRIMARY_KEY);

        $columnSchemaB = new ColumnSchema();
        $columnSchemaB->setName("baz");
        $columnSchemaB->setDataType(DataType::VARCHAR());
        $columnSchemaB->setLength(12);

        $columnSchemaC = new ColumnSchema();
        $columnSchemaC->setName("bar");
        $columnSchemaC->setDataType(DataType::DATETIME());
        $columnSchemaC->setLength(19);

        $indexSchema = new IndexSchema();
        $indexSchema->setName("idx_foo");
        $indexSchema->setColumns([0]); # n'th index in $columnData's
        $indexSchema->setEngine(IndexEngine::BTREE());
        $indexSchema->setType(Type::UNIQUE());
        $indexSchema->setKeyLength(4); 

        $tableSchema = new TableSchema(
            new FileResourceProxy(fopen("php://memory", "w")), # column-schema-file
            new FileResourceProxy(fopen("php://memory", "w"))  # index-schema-file
        );
        $tableSchema->addColumnSchema($columnSchemaA);
        $tableSchema->addColumnSchema($columnSchemaB);
        $tableSchema->addColumnSchema($columnSchemaC);
        $tableSchema->addIndexSchema($indexSchema);

        $this->table = new Table(
            $tableSchema,
            [
                new ColumnData(
                    new FileResourceProxy(fopen("php://memory", "w")),
                    $columnSchemaA
                ),
                new ColumnData(
                    new FileResourceProxy(fopen("php://memory", "w")),
                    $columnSchemaB
                ),
                new ColumnData(
                    new FileResourceProxy(fopen("php://memory", "w")),
                    $columnSchemaC
                ),
            ],
            [
                new BTree(
                    new FileResourceProxy(fopen("php://memory", "w")),
                    $tableSchema,
                    $indexSchema
                ),
            ],
            new FileResourceProxy(fopen("php://memory", "w")), # auto-increment / tablestati
            new FileResourceProxy(fopen("php://memory", "w"))  # deleted-rows storage
        );
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testAddColumn(
        array $fixtureRows,
        ColumnSchema $columnSchema,
        array $appendedRows,
        array $expectedRows
    ) {
        /* @var $table Table */
        $table = $this->table;

        ### PREPARE

        foreach ($fixtureRows as $row) {
            $table->addRowData($row);
        }

        ### EXECUTE

        $table->addColumn(
            $columnSchema,
            new ColumnData(
                new FileResourceProxy(fopen("php://memory", "w")),
                $columnSchema
            )
        );

        foreach ($appendedRows as $row) {
            $table->addRowData($row);
        }

        ### CHECK RESULTS

        $actualRows = array();
        foreach ($table as $row) {
            $actualRows[] = array_values($row);
        }

        $this->assertEquals($expectedRows, $actualRows);
    }

    /**
     * @group intregration.table
     * @group intregration.table.modify_column
     * @dataProvider dataProviderModifyColumn
     */
    public function testModifyColumn(
        array $fixtureRows,
        ColumnSchema $columnSchema,
        array $appendedRows,
        array $expectedRows
    ) {
        /* @var $table Table */
        $table = $this->table;

        ### PREPARE

        foreach ($fixtureRows as $row) {
            $table->addRowData($row);
        }

        ### EXECUTE

        $table->modifyColumn(
            $columnSchema,
            new ColumnData(
                new FileResourceProxy(fopen("php://memory", "w")),
                $columnSchema
            )
        );

        foreach ($appendedRows as $row) {
            $table->addRowData($row);
        }

        ### CHECK RESULTS

        $actualRows = array();
        foreach ($table as $row) {
            $actualRows[] = array_values($row);
        }

        $this->assertEquals($expectedRows, $actualRows); 
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testGetColumnData()
    {
        /* @var $table Table */
        $table = $this->table;

        $this->markTestIncomplete();

        ### EXECUTE

        $table;
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testSetCellData()
    {
        /* @var $table Table */
        $table = $this->table;

        $this->markTestIncomplete();

        ### EXECUTE

        $table;   
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testDoesRowExists()
    {
        /* @var $table Table */
        $table = $this->table;

        $this->markTestIncomplete();

        ### EXECUTE

        $table;
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testGetRowCount()
    {
        /* @var $table Table */
        $table = $this->table;

        $this->markTestIncomplete();

        ### EXECUTE

        $table;
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testGetNamedRowData()
    {
        /* @var $table Table */
        $table = $this->table;

        $this->markTestIncomplete();

        ### EXECUTE

        $table; 
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testGetRowData()
    {
        /* @var $table Table */
        $table = $this->table;

        $this->markTestIncomplete();

        ### EXECUTE

        $table;
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testSetRowData()
    {
        /* @var $table Table */
        $table = $this->table;

        $this->markTestIncomplete();

        ### EXECUTE

        $table;
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testAddRowData()
    {
        /* @var $table Table */
        $table = $this->table;

        $this->markTestIncomplete();

        ### EXECUTE

        $table;
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testRemoveRow()
    {
        /* @var $table Table */
        $table = $this->table;

        $this->markTestIncomplete();

        ### EXECUTE

        $table;
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testIncrementAutoIncrementId()
    {
        /* @var $table Table */
        $table = $this->table;

        $this->markTestIncomplete();

        ### EXECUTE

        $table;
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testGetAutoIncrementId()
    {
        /* @var $table Table */
        $table = $this->table;

        $this->markTestIncomplete();

        ### EXECUTE

        $table;
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testSeek()
    {
        /* @var $table Table */
        $table = $this->table;

        $this->markTestIncomplete();

        ### EXECUTE

        $table;
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testTell()
    {
        /* @var $table Table */
        $table = $this->table;

        $this->markTestIncomplete();

        ### EXECUTE

        $table;
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testCount()
    {
        /* @var $table Table */
        $table = $this->table;

        $this->markTestIncomplete();

        ### EXECUTE

        $table;
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testIterate()
    {
        /* @var $table Table */
        $table = $this->table;

        $this->markTestIncomplete();

        ### EXECUTE

        $table;
    }

    ### DATA-PROVIDER

    public function dataProviderAddColumn()
    {
        $columnSchema = new ColumnSchema();
        $columnSchema->setName("faz");
        $columnSchema->setDataType(DataType::BOOLEAN());

        return array(
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                ],
                $columnSchema,
                [
                    [789, "amet", "1990-10-02 10:20:30", true],
                    [234, "",     null,                  false],
                ],
                [
                    ['123', "Lorem ipsum", null,                  null],
                    ['456', "dolor sit",   "2015-06-07 12:34:56", null],
                    ['789', "amet",        "1990-10-02 10:20:30", '1'],
                    ['234', "",            null,                  false],
                ]
            ]
        );
    }

    public function dataProviderModifyColumn()
    {
        $columnSchema = new ColumnSchema();
        $columnSchema->setName("baz");
        $columnSchema->setDataType(DataType::VARCHAR());
        $columnSchema->setLength(5);

        return array(
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                ],
                $columnSchema,
                [
                    [789, "amet", "1990-10-02 10:20:30"],
                    [234, "",     null],
                ],
                [
                    ['123', "Lorem", null],
                    ['456', "dolor", "2015-06-07 12:34:56"],
                    ['789', "amet",  "1990-10-02 10:20:30"],
                    ['234', "",      null],
                ],
            ]
        );
    }

}

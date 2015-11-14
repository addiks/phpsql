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
use Addiks\PHPSQL\Column\ColumnDataInterface;

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

    /**
     * @group intregration.table
     * @group intregration.table.get_column_data
     * @dataProvider dataProviderGetColumnData
     */
    public function testGetColumnData($columnId, $columnName)
    {
        /* @var $table Table */
        $table = $this->table;

        ### EXECUTE

        /* @var $columnData */
        $columnData = $table->getColumnData($columnId);

        ### CHECK RESULTS

        $this->assertTrue($columnData instanceof ColumnDataInterface);

        /* @var $columnSchema ColumnSchema */
        $columnSchema = $columnData->getColumnSchema();

        $this->assertEquals($columnName, $columnSchema->getName());
    }

    public function dataProviderGetColumnData()
    {
        return array(
            [0, "foo"],
            [1, "baz"],
            [2, "bar"],
        );
    }

    /**
     * @group intregration.table
     * @group intregration.table.get_cell_data
     * @dataProvider dataProviderSetGetCellData
     */
    public function testSetGetCellData($rowId, $columnId, $cellData)
    {
        /* @var $table Table */
        $table = $this->table;

        ### EXECUTE

        $table->setCellData($rowId, $columnId, $cellData);

        $actualCellData = $table->getCellData($rowId, $columnId);

        ### CHECK RESULTS

        $this->assertEquals($cellData, $actualCellData);
    }

    public function dataProviderSetGetCellData()
    {
        return array(
            [0, 0, "123"],
            [0, 1, "foo"],
            [1, 0, "456"],
            [1, 1, "bar"],
            [1024, 1, "baz"],
        );
    }

    /**
     * @group intregration.table
     * @group intregration.table.does_row_exist
     * @dataProvider dataProviderDoesRowExists
     */
    public function testDoesRowExists(array $fixtureRows, $needleRowId, $expectedResult)
    {
        /* @var $table Table */
        $table = $this->table;

        ### PREPARE

        foreach ($fixtureRows as $row) {
            $table->addRowData($row);
        }

        ### EXECUTE

        $actualResult = $table->doesRowExists($needleRowId);

        ### CHECK RESULTS

        $this->assertEquals($expectedResult, $actualResult);
    }

    public function dataProviderDoesRowExists()
    {
        return array(
            [
                [],
                3,
                false
            ],
            [
                [],
                0,
                false
            ],
            [
                [
                    [123, "Lorem ipsum", null]
                ],
                0,
                true
            ],
            [
                [
                    [123, "Lorem ipsum", null]
                ],
                1,
                false
            ],
            [
                [
                    [123, "Lorem ipsum", null]
                ],
                2,
                false
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                ],
                0,
                true
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                ],
                1,
                true
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                ],
                2,
                false
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                ],
                3,
                false
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                ],
                -1,
                false
            ],
        );
    }

    /**
     * @group intregration.table
     * @group intregration.table.get_row_count
     * @dataProvider dataProviderGetRowCount
     */
    public function testGetRowCount(array $fixtureRows, $expectedResult)
    {
        /* @var $table Table */
        $table = $this->table;

        ### PREPARE

        foreach ($fixtureRows as $row) {
            $table->addRowData($row);
        }

        ### EXECUTE

        $actualResult = $table->getRowCount();
  
        ### CHECK RESULTS

        $this->assertEquals($expectedResult, $actualResult);
    }

    public function dataProviderGetRowCount()
    {
        return array(
            [
                [],
                0
            ],
            [
                [
                    [123, "Lorem ipsum", null]
                ],
                1
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                ],
                2
            ],
        );
    }

    /**
     * @group intregration.table
     * @group intregration.table.get_named_row_data
     * @dataProvider dataProviderGetNamedRowData
     */
    public function testGetNamedRowData(array $fixtureRows, $rowId, $expectedResult)
    {
        /* @var $table Table */
        $table = $this->table;

        ### PREPARE

        foreach ($fixtureRows as $row) {
            $table->addRowData($row);
        }

        ### EXECUTE

        $actualResult = $table->getNamedRowData($rowId);

        ### CHECK RESULTS

        $this->assertEquals($expectedResult, $actualResult);
    }

    public function dataProviderGetNamedRowData()
    {
        return array(
            [
                [
                    [123, "Lorem ipsum", null]
                ],
                0,
                [
                    'foo' => "123",
                    'baz' => "Lorem ipsum",
                    'bar' => ""
                ]
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                ],
                1,
                [
                    'foo' => "456",
                    'baz' => "dolor sit",
                    'bar' => "2015-06-07 12:34:56"
                ]
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                    ['789', "amet",  "1990-10-02 10:20:30"],
                ],
                1,
                [
                    'foo' => "456",
                    'baz' => "dolor sit",
                    'bar' => "2015-06-07 12:34:56"
                ]
            ],
        );
    }

    /**
     * @group intregration.table
     * @group intregration.table.get_row_data
     * @dataProvider dataProviderGetRowData
     */
    public function testGetRowData(array $fixtureRows, $rowId, $expectedResult)
    {
        /* @var $table Table */
        $table = $this->table;

        ### PREPARE

        foreach ($fixtureRows as $row) {
            $table->addRowData($row);
        }

        ### EXECUTE

        $actualResult = $table->getRowData($rowId);

        ### CHECK RESULTS

        $this->assertEquals($expectedResult, $actualResult);
    }

    public function dataProviderGetRowData()
    {
        return array(
            [
                [
                    [123, "Lorem ipsum", null]
                ],
                0,
                [
                    "123",
                    "Lorem ipsum",
                    ""
                ]
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                ],
                1,
                [
                    "456",
                    "dolor sit",
                    "2015-06-07 12:34:56"
                ]
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                    ['789', "amet",  "1990-10-02 10:20:30"],
                ],
                1,
                [
                    "456",
                    "dolor sit",
                    "2015-06-07 12:34:56"
                ]
            ],
        );
    }

    /**
     * @group intregration.table
     * @group intregration.table.set_row_data
     * @dataProvider dataProviderSetRowData
     */
    public function testSetRowData($rowId, array $rowData)
    {
        /* @var $table Table */
        $table = $this->table;

        ### EXECUTE

        $table->setRowData($rowId, $rowData);

        ### CHECK RESULTS

        $actualResult = $table->getRowData($rowId);

        $this->assertEquals($rowData, $actualResult);
    }

    public function dataProviderSetRowData()
    {
        return array(
            [
                0,
                [123, "Lorem ipsum", null],
            ],
            [
                1,
                [456, "dolor sit",   "2015-06-07 12:34:56"],
            ],
            [
                2,
                ['789', "amet",  "1990-10-02 10:20:30"],
            ],
        );
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_row_data
     * @dataProvider dataProviderAddRowData
     */
    public function testAddRowData(array $fixtureRows, array $rowData, $expectedResult)
    {
        /* @var $table Table */
        $table = $this->table;

        ### PREPARE

        foreach ($fixtureRows as $row) {
            $table->addRowData($row);
        }

        ### EXECUTE

        $table->addRowData($rowData);

        ### CHECK RESULTS

        $actualResult = array();
        foreach ($table as $rowId => $row) {
            $actualResult[$rowId] = $row;
        }
        
        $this->assertEquals($expectedResult, $actualResult);
    }

    public function dataProviderAddRowData()
    {
        return array(
            [
                [],
                [123, "Lorem ipsum", null],
                [
                    [
                        'foo' => "123",
                        'baz' => "Lorem ipsum",
                        'bar' => ""
                    ]
                ],
            ],
            [
                [
                    [123, "Lorem ipsum", null]
                ],
                [456, "dolor sit",   "2015-06-07 12:34:56"],
                [
                    [
                        'foo' => "123",
                        'baz' => "Lorem ipsum",
                        'bar' => ""
                    ],
                    [
                        'foo' => "456",
                        'baz' => "dolor sit",
                        'bar' => "2015-06-07 12:34:56"
                    ],
                ],
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                ],
                ['789', "amet",  "1990-10-02 10:20:30"],
                [
                    [
                        'foo' => "123",
                        'baz' => "Lorem ipsum",
                        'bar' => ""
                    ],
                    [
                        'foo' => "456",
                        'baz' => "dolor sit",
                        'bar' => "2015-06-07 12:34:56"
                    ],
                    [
                        'foo' => "789",
                        'baz' => "amet",
                        'bar' => "1990-10-02 10:20:30"
                    ],
                ],
            ],
        );
    }

    /**
     * @group intregration.table
     * @group intregration.table.remove_row
     * @dataProvider dataProviderRemoveRow
     */
    public function testRemoveRow(array $fixtureRows, $rowIdToDelete, $expectedResult)
    {
        /* @var $table Table */
        $table = $this->table;

        ### PREPARE

        foreach ($fixtureRows as $row) {
            $table->addRowData($row);
        }

        ### EXECUTE

        $table->removeRow($rowIdToDelete);

        ### CHECK RESULTS

        $actualResult = array();
        foreach ($table as $rowId => $row) {
            $actualResult[$rowId] = array_values($row);
        }
        
        $this->assertEquals($expectedResult, $actualResult);
    }

    public function dataProviderRemoveRow()
    {
        return array(
            [
                [
                    [123, "Lorem ipsum", null]
                ],
                0,
                []
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                ],
                0,
                [
                    1 => [456, "dolor sit",   "2015-06-07 12:34:56"],
                ]
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                ],
                1,
                [
                    [123, "Lorem ipsum", null],
                ]
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                    [789, "amet",        "1990-10-02 10:20:30"],
                ],
                0,
                [
                    1 => [456, "dolor sit",   "2015-06-07 12:34:56"],
                    2 => [789, "amet",        "1990-10-02 10:20:30"],
                ]
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                    [789, "amet",        "1990-10-02 10:20:30"],
                ],
                1,
                [
                    0 => [123, "Lorem ipsum", null],
                    2 => [789, "amet",        "1990-10-02 10:20:30"],
                ]
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                    [789, "amet",        "1990-10-02 10:20:30"],
                ],
                2,
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                ]
            ],
        );
    }

    /**
     * @group intregration.table
     * @group intregration.table.increment_auto_increment
     * @dataProvider dataProviderIncrementAutoIncrementId
     */
    public function testIncrementIncrementAutoIncrementId(
        $beforeAutoIncrement,
        $expectedResult
    ) {
        /* @var $table Table */
        $table = $this->table;

        $table->setAutoIncrementId($beforeAutoIncrement);

        ### EXECUTE

        $table->incrementAutoIncrementId();

        ### CHECK RESULTS

        $actualResult = $table->getAutoIncrementId();

        $this->assertEquals($expectedResult, $actualResult);
    }

    public function dataProviderIncrementAutoIncrementId()
    {
        return array(
            [0, 1],
            [1, 2],
            [2, 3],
            [123123122, 123123123],
        );
    }

    /**
     * @group intregration.table
     * @group intregration.table.get_auto_increment
     * @dataProvider dataProviderGetAutoIncrementId
     */
    public function testGetAutoIncrementId($autoIncrement)
    {
        /* @var $table Table */
        $table = $this->table;

        $table->setAutoIncrementId($autoIncrement);

        ### EXECUTE

        $actualResult = $table->getAutoIncrementId();

        ### CHECK RESULTS

        $this->assertEquals($autoIncrement, $actualResult);
    }

    public function dataProviderGetAutoIncrementId()
    {
        return array(
            [0],
            [1],
            [2],
            [3],
            [1024],
            [123123123123],
        );
    }

    /**
     * @group intregration.table
     * @group intregration.table.seek
     * @dataProvider dataProviderSeek
     */
    public function testSeek(array $fixtureRows, $seekIndex, $expectedResult)
    {
        /* @var $table Table */
        $table = $this->table;

        ### PREPARE

        foreach ($fixtureRows as $row) {
            $table->addRowData($row);
        }

        ### EXECUTE

        $table->seek($seekIndex);

        ### CHECK RESULTS

        $actualResult = array_values($table->current());
        
        $this->assertEquals($expectedResult, $actualResult);
    }

    public function dataProviderSeek()
    {
        return array(
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                    [789, "amet",        "1990-10-02 10:20:30"],
                ],
                0,
                [123, "Lorem ipsum", null]
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                    [789, "amet",        "1990-10-02 10:20:30"],
                ],
                1,
                [456, "dolor sit",   "2015-06-07 12:34:56"],
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                    [789, "amet",        "1990-10-02 10:20:30"],
                ],
                2,
                [789, "amet",        "1990-10-02 10:20:30"],
            ],
        );
    }

    /**
     * @group intregration.table
     * @group intregration.table.tell
     * @dataProvider dataProviderTell
     */
    public function testTell(array $fixtureRows, $seekIndex, $expectedResult)
    {
        /* @var $table Table */
        $table = $this->table;

        ### PREPARE

        foreach ($fixtureRows as $row) {
            $table->addRowData($row);
        }

        $table->seek($seekIndex);

        ### EXECUTE

        $actualResult = $table->tell();

        ### CHECK RESULTS
        
        $this->assertEquals($expectedResult, $actualResult);
    }

    public function dataProviderTell()
    {
        return array(
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                    [789, "amet",        "1990-10-02 10:20:30"],
                ],
                0,
                0
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                    [789, "amet",        "1990-10-02 10:20:30"],
                ],
                1,
                1
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                    [789, "amet",        "1990-10-02 10:20:30"],
                ],
                2,
                2
            ],
        );
    }

    /**
     * @group intregration.table
     * @group intregration.table.count
     * @dataProvider dataProviderCount
     */
    public function testCount(array $fixtureRows, $expectedResult)
    {
        /* @var $table Table */
        $table = $this->table;

        ### PREPARE

        foreach ($fixtureRows as $row) {
            $table->addRowData($row);
        }

        ### EXECUTE

        $actualResult = $table->count();

        ### CHECK RESULTS
        
        $this->assertEquals($expectedResult, $actualResult);
    }

    public function dataProviderCount()
    {
        return array(
            [
                [
                ],
                0
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                ],
                1
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                ],
                2
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                    [789, "amet",        "1990-10-02 10:20:30"],
                ],
                3
            ],
        );
    }

    /**
     * @group intregration.table
     * @group intregration.table.iterate
     * @dataProvider dataProviderIterate
     */
    public function testIterate(array $fixtureRows, array $expectedResult)
    {
        /* @var $table Table */
        $table = $this->table;

        ### PREPARE

        foreach ($fixtureRows as $row) {
            $table->addRowData($row);
        }

        ### EXECUTE

        $actualResult = array();
        foreach ($table as $rowId => $row) {
            $actualResult[$rowId] = array_values($row);
        }

        ### CHECK RESULTS
        
        $this->assertEquals($expectedResult, $actualResult);
    }

    public function dataProviderIterate()
    {
        return array(
            [
                [
                ],
                [
                ],
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                ],
                [
                    [123, "Lorem ipsum", null],
                ],
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                ],
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                ],
            ],
            [
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                    [789, "amet",        "1990-10-02 10:20:30"],
                ],
                [
                    [123, "Lorem ipsum", null],
                    [456, "dolor sit",   "2015-06-07 12:34:56"],
                    [789, "amet",        "1990-10-02 10:20:30"],
                ],
            ],
        );
    }

}

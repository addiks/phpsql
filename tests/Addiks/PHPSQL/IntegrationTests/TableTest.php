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

        ### EXECUTE

        foreach ($fixtureRows as $row) {
            $table->addRowData($row);
        }

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
     * @dataProvider dataProviderAddColumn
     */
    public function testModifyColumn()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testGetColumnData()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testSetCellData()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testDoesRowExists()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testGetRowCount()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testGetNamedRowData()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testGetRowData()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testSetRowData()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testAddRowData()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testRemoveRow()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testIncrementAutoIncrementId()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testGetAutoIncrementId()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testSeek()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testTell()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testCount()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    /**
     * @group intregration.table
     * @group intregration.table.add_column
     * @dataProvider dataProviderAddColumn
     */
    public function testIterate()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
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

}

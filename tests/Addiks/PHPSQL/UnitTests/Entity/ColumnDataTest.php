<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\Entity;

use ErrorException;
use PHPUnit_Framework_TestCase;
use Addiks\PHPSQL\Column\ColumnData;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;
use Addiks\PHPSQL\Column\ColumnSchema;
use Addiks\PHPSQL\Value\Enum\Page\Column\DataType;

class ColumnDataTest extends PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        $file = new FileResourceProxy(fopen("php://memory", "w"));

        $columnSchema = new ColumnSchema();
        $columnSchema->setName("test_column");
        $columnSchema->setIndex(0);
        $columnSchema->setDataType(DataType::VARCHAR());
        $columnSchema->setLength(10);

        $this->columnData = new ColumnData($file, $columnSchema);
    }

    protected $columnData;

    ### TESTS

    /**
     * @group unittests.column_data
     * @group unittests.column_data.set
     * @dataProvider dataProviderSetCellData
     */
    public function testSetCellData($index, $data)
    {
        ### EXECUTE

        $this->columnData->setCellData($index, $data);

        ### CHECK RESULTS

        $this->assertEquals($data, $this->columnData->getCellData($index));
    }

    /**
     * @group unittests.column_data
     * @group unittests.column_data.remove
     * @dataProvider dataProviderRemoveCell
     * @depends testSetCellData
     */
    public function testRemoveCell($cells, $removeIndex)
    {
        ### PREPARE

        foreach ($cells as $index => $cellData) {
            $this->columnData->setCellData($index, $cellData);
        }

        ### EXECUTE

        $this->columnData->removeCell($removeIndex);

        ### CHECK RESULTS

        $actualCellData = $this->columnData->getCellData($removeIndex);

        $this->assertEmpty($actualCellData);
    }

    /**
     * @group unittests.column_data
     * @group unittests.column_data.iterate
     * @dataProvider dataProviderIterate
     * @depends testSetCellData
     */
    public function testIterate($cells)
    {
        ### PREPARE
    
        foreach ($cells as $index => $cellData) {
            $this->columnData->setCellData($index, $cellData);
        }

        ### EXECUTE

        $actualResult = array();
        $iterationCount = 0;
        foreach ($this->columnData as $index => $cellData) {
            $actualResult[$index] = $cellData;

            $iterationCount++;
            if ($iterationCount > count($cells)) {
                throw new ErrorException("Iterated over more pages than should be in column-data!");
            }
        }

        $this->assertEquals($cells, $actualResult);
    }

    /**
     * @group unittests.column_data
     * @group unittests.column_data.iterate
     * @dataProvider dataProviderIterateAfterRemove
     * @depends testIterate
     * @depends testRemoveCell
     */
    public function testIterateAfterRemove($cells, $removeIndex, $expectedCells)
    {
        ### PREPARE

        foreach ($cells as $index => $cellData) {
            $this->columnData->setCellData($index, $cellData);
        }

        $this->columnData->removeCell($removeIndex);
    
        ### EXECUTE

        $actualResult = array();
        $iterationCount = 0;
        foreach ($this->columnData as $index => $cellData) {
            $actualResult[$index] = $cellData;

            $iterationCount++;
            if ($iterationCount > count($cells)) {
                throw new ErrorException("Iterated over more pages than should be in column-data!");
            }
        }

        $this->assertEquals($expectedCells, $actualResult);
    }

    ### DATA PROVIDER

    public function dataProviderSetCellData()
    {
        return array(
            [2, "Lorem"],
            [6, "ipsum"],
            [9, "dolor sit"],
            [10, "amet"],
        );
    }

    public function dataProviderRemoveCell()
    {
        return array(
            [
                [
                    1 => 'Lorem', 
                    2 => 'ipsum', 
                    4 => 'dolor', 
                ],
                2
            ],
            [
                [
                    2 => 'foo', 
                    3 => 'bar', 
                    7 => 'baz', 
                ],
                2
            ],
            [
                [
                    1 => 'Hello', 
                    2 => 'World!', 
                    4 => 'Hello', 
                ],
                4
            ],
        );
    }

    public function dataProviderIterate()
    {
        return array(
            [
                [
                    1 => 'Lorem', 
                    2 => 'ipsum', 
                    4 => 'dolor',
                ]
            ],
            [
                [
                    2 => 'foo', 
                    3 => 'bar', 
                    7 => 'baz',
                ]
            ],
            [
                [
                    1 => 'Hello', 
                    4 => 'Hello',
                    2 => 'World!', 
                ]
            ],
        );
    }

    public function dataProviderIterateAfterRemove()
    {
        return array(
            [
                [
                    1 => 'Lorem', 
                    2 => 'ipsum', 
                    4 => 'dolor',
                ],
                2,
                [
                    1 => 'Lorem', 
                    4 => 'dolor',
                ],
            ],
            [
                [
                    1 => 'Lorem', 
                    2 => 'ipsum', 
                    4 => 'dolor',
                ],
                1,
                [
                    2 => 'ipsum', 
                    4 => 'dolor',
                ],
            ],
            [
                [
                    4 => 'dolor',
                ],
                4,
                [
                ],
            ],
            [
                [
                    1 => 'Lorem', 
                    2 => 'ipsum', 
                    4 => 'dolor',
                ],
                3,
                [
                    1 => 'Lorem', 
                    2 => 'ipsum', 
                    4 => 'dolor',
                ],
            ],
        );
    }

}

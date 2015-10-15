<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\UnitTests\Entity;

use PHPUnit_Framework_TestCase;
use Addiks\PHPSQL\Index\QuickSort;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;
use Addiks\PHPSQL\Column\ColumnSchema;
use Addiks\PHPSQL\Value\Enum\Page\Column\DataType;

class QuickSortTest extends PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        $file = new FileResourceProxy(fopen("php://memory", "w"));

        $column1 = new ColumnSchema();
        $column1->setName("foo");
        $column1->setDataType(DataType::INTEGER());
        $column1->setLength(4);

        $column2 = new ColumnSchema();
        $column2->setName("bar");
        $column2->setDataType(DataType::VARCHAR());
        $column2->setLength(12);

        $columns = [
            [$column1, 'ASC'],
            [$column2, 'DESC'],
        ];

        $this->index = new QuickSort($file, $columns);
    }

    /**
     * @var QuickSort
     */
    protected $index;

    /**
     * @dataProvider dataProviderQuickSort
     */
    public function testQuickSort(array $rows, array $expectedRowIndexes)
    {
        
        ### PREPARE

        $actualRowIndexes = array();

        foreach ($rows as $rowIndex => $row) {
            $this->index->addRow($rowIndex, $row);
        }

        ### EXECUTE
    
        try {
            $this->index->sort();

            foreach ($this->index as $rowIndex) {
                $actualRowIndexes[] = $rowIndex;
            }
            
        } catch (Exception $exception) {
            throw $exception;
        }

        ### CHECK RESULTS

        $this->assertEquals($expectedRowIndexes, $actualRowIndexes);
    }

    public function dataProviderQuickSort()
    {
        return [
            [
                [
                    358 => [234, 'abc'],
                    265 => [234, 'ghi'],
                    314 => [123, 'def'],
                    159 => [234, 'jkl'],
                ],
                [314, 159, 265, 358]
            ]
        ];
    }
}

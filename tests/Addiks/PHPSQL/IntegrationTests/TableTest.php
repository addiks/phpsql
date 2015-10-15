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

class TableTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->markTestIncomplete();

        $autoIncrementFile = new FileResourceProxy(fopen("php://memory", "w"));
        
        $deletedRowsFile = new FileResourceProxy(fopen("php://memory", "w"));
        
        $columnDatas = array();
        $indices = array();

        $this->table = new Table(
            $tableSchema,
            $columnDatas,
            $indices,
            $autoIncrementFile,
            $deletedRowsFile
        );
    }

    public function testAddColumnDefinition()
    {
        $this->markTestIncomplete();
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    public function testModifyColumnDefinition()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    public function testGetColumnData()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    public function testSetCellData()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    public function testDoesRowExists()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    public function testGetRowCount()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    public function testGetNamedRowData()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    public function testGetRowData()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    public function testSetRowData()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    public function testAddRowData()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    public function testRemoveRow()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    public function testIncrementAutoIncrementId()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    public function testGetAutoIncrementId()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    public function testSeek()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    public function testTell()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    public function testCount()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

    public function testIterate()
    {
        $this->markTestIncomplete();

        ### EXECUTE

        $this->table;    
    }

}

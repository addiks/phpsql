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

use Exception;
use PHPUnit_Framework_TestCase;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;
use Addiks\PHPSQL\Value\Enum\Sql\IndexType;
use Addiks\PHPSQL\Value\Enum\Page\Column\DataType;
use Addiks\PHPSQL\Table\TableSchema;
use Addiks\PHPSQL\Value\Enum\Page\Index\Type;
use Addiks\PHPSQL\Column\ColumnSchema;
use Addiks\PHPSQL\Index\IndexSchema;
use Addiks\PHPSQL\Index\BTree;
use Addiks\PHPSQL\Value\Enum\Page\Index\IndexEngine;

class BTreeTest extends PHPUnit_Framework_TestCase
{

    public function setUp()
    {

        $forkRate        = 16;
        $file            = new FileResourceProxy(fopen("php://memory", "w"));
        $doublesFile     = new FileResourceProxy(fopen("php://memory", "w"));
        $tableSchemaFile = new FileResourceProxy(fopen("php://memory", "w"));
        $indexSchemaFile = new FileResourceProxy(fopen("php://memory", "w"));

        $tableSchema = new TableSchema($tableSchemaFile, $indexSchemaFile);

        $columnPageA = new ColumnSchema();
        $columnPageA->setName("columnB");
        $columnPageA->setIndex(0);
        $columnPageA->setDataType(DataType::INT());
        $columnPageA->setLength(4);
        $tableSchema->addColumnSchema($columnPageA);

        $columnPageB = new ColumnSchema();
        $columnPageB->setName("columnC");
        $columnPageB->setIndex(1);
        $columnPageB->setDataType(DataType::VARCHAR());
        $columnPageB->setLength(4);
        $tableSchema->addColumnSchema($columnPageB);

        $indexPage = new IndexSchema();
        $indexPage->setName("test-index");
        $indexPage->setColumns([0, 1]);
        $indexPage->setType(Type::INDEX());
        $indexPage->setEngine(IndexEngine::BTREE());

        $this->btree = new BTree($file, $tableSchema, $indexPage, $forkRate);
        $this->btree->setDoublesFile($doublesFile);
        $this->btree->setIsDevelopmentMode(true);
    }

    /**
     * @group unittests.btree
     * @group unittests.btree.insert
     */
    public function testInsert()
    {

        try {
            $this->btree->insert("foo", "bar");
            $this->btree->selfTest();

        } catch (Exception $exception) {
            $this->btree->dump(true, true, true);
            throw $exception;
        }

        ### CHECK RESULTS

        $results = $this->btree->search("foo");

        $this->assertContains(str_pad("bar", 8, "\0", STR_PAD_LEFT), $results, "Could not insert value into b-tree!");
    }

    /**
     * @depends testInsert
     * @group unittests.btree
     * @group unittests.btree.insert
     */
    public function testMultipleInserts()
    {
        
        try {
            $this->btree->insert("foo", "abc");
            $this->btree->insert("bar", "def");
            $this->btree->insert("bar", "ghi");
            $this->btree->selfTest();

        } catch (Exception $exception) {
            $this->btree->dump(true, true, true);
            throw $exception;
        }

    }
 
    /**
     * @depends testMultipleInserts
     * @group unittests.btree
     * @group unittests.btree.search
     */
    public function testSearch()
    {

        ### PREPARE

        $this->btree->insert("foo", "abc");
        $this->btree->insert("bar", "def");
        $this->btree->insert("bar", "ghi");
        $this->btree->insert("baz", "jkl");

        ### EXECUTE

        try {
            $results = $this->btree->search("bar");
            $this->btree->selfTest();

        } catch (Exception $exception) {
            $this->btree->dump(true, true, true);
            throw $exception;
        }

        ### CHECK RESULTS

        $this->assertContains(str_pad("def", 8, "\0", STR_PAD_LEFT), $results, "Could not search value in b-tree!");
        $this->assertContains(str_pad("ghi", 8, "\0", STR_PAD_LEFT), $results, "Could not search value in b-tree!");
    }

    /**
     * @group unittests.btree
     * @group unittests.btree.remove
     */
    public function testRemove()
    {

        ### PREPARE

        $this->btree->insert("foo", "abc");
        $this->btree->insert("bar", "def");
        $this->btree->insert("bar", "ghi");

        ### EXECUTE

        try {
            $this->btree->remove("bar", "def");
            $this->btree->selfTest();

        } catch (Exception $exception) {
            $this->btree->dump(true, true, true);
            throw $exception;
        }

        ### CHECK RESULTS

        $results = $this->btree->search("bar");
        $this->assertNotContains(str_pad("def", 8, "\0", STR_PAD_LEFT), $results, "Could not remove value from b-tree!");
        $this->assertContains(str_pad("ghi", 8, "\0", STR_PAD_LEFT), $results, "Could not correctly remove value from b-tree!");
    }
   
}

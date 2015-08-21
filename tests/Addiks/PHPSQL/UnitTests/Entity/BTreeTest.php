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
use Addiks\PHPSQL\Entity\Index\BTree;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;

class BTreeTest extends PHPUnit_Framework_TestCase
{

    public function setUp()
    {

        $keyLength   = 8;
        $forkRate    = 16;
        $file        = new FileResourceProxy(fopen("php://memory", "w"));
        $doublesFile = new FileResourceProxy(fopen("php://memory", "w"));

        $this->btree = new BTree($file, $keyLength, $forkRate);
        $this->btree->setDoublesFile($doublesFile);
        $this->btree->setIsDevelopmentMode(true);
    }

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

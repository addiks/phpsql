<?php
/**
 * Copyright (C) 2013  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 * @package Addiks
 */

namespace Addiks\PHPSQL\UnitTests\Entity;

use Addiks\PHPSQL\Value\Text\Filepath;
use Addiks\PHPSQL\Entity\Storage;
use Addiks\PHPSQL\Entity\Index\HashTable;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;
use Addiks\PHPSQL\Filesystem\InmemoryFilesystem;

class HashTable extends PHPUnit_Framework_TestCase
{
    
    public function setUp()
    {
        $fileSystem = new InmemoryFilesystem();

        /* @var $file FileResourceProxy */
        $file = $fileSystem->getFile("/tests/someHashTable");

        $this->hashTable = new HashTable($file);
    }

    /** @var HashTable */
    private $hashTable;
    
    public function testSearch()
    {
        
        ### INPUT
        
        $keyLength = 32;
        $value = "abc";
        $input = array(
            'abc' => '123',
            'def' => '456',
            'ghi' => '789',
            'abc' => 'FOO',
        );
        $expectedResult = array(
            '123', 'FOO'
        );
        
        ### PREPARE
        
        /* @var $hashTable HashTable */
        $hashTable = $this->hashTable;
        
        foreach ($input as $key => $value) {
            $hashTable->insert($key, $value);
        }
        
        ### EXECUTE
        
        $actualResult = $hashTable->search($value);
        
        ### COMPARE
        
        $this->assertEqual($actualResult, $expectedResult);
    }
    
    public function testInsert()
    {
    
        ### PREPARE
    
        ### EXECUTE
    
        ### COMPARE
    
    }
    
    public function testRemove()
    {
    
        ### PREPARE
    
        ### EXECUTE
    
        ### COMPARE
    
    }
}

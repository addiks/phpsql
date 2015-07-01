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

namespace Addiks\PHPSQL\Service\TestCase\Entity;

use Addiks\Common\Value\Text\Filepath;

use Addiks\PHPSQL\Entity\Storage;

use Addiks\Common\TestCase;

class HashTable extends TestCase{
	
	public function testSearch(){
		
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
		$hashTable = $this->getHashTable($keyLength);
		
		foreach($input as $key => $value){
			$hashTable->insert($key, $value);
		}
		
		### EXECUTE
		
		$actualResult = $hashTable->search($value);
		
		### COMPARE
		
		$this->assertEqual($actualResult, $expectedResult);
	}
	
	public function testInsert(){
	
		### PREPARE
	
		### EXECUTE
	
		### COMPARE
	
	}
	
	public function testRemove(){
	
		### PREPARE
	
		### EXECUTE
	
		### COMPARE
	
	}
	
	### HELPER
	
	public function tearDown(){
		$this->hashTable = null;
	}
	
	/** @var HashTable */
	private $hashTable;
	
	protected function getHashTable($keyLength=null){
		if(!is_null($keyLength)){
			$dataDir = $this->getMockFramework()->getDataDir();
			$storage = new Storage(Filepath::factory("{$dataDir}/HashTable"));
			$storage->clear();
			$this->hashTable = new HashTable($storage, $keyLength);
			return $this->hashTable;
		}
		if(is_null($this->hashTable)){
			$dataDir = $this->getMockFramework()->getDataDir();
			$storage = new Storage(Filepath::factory("{$dataDir}/HashTable"));
			$storage->clear();
			$this->hashTable = new HashTable($storage, 32);
		}
		return $this->hashTable;
	}
	
	
}
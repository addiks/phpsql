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

namespace Addiks\Database\Entity\Index;

use Addiks\Common\Entity;

use Addiks\Database\Entity\Index\IndexInterface;

class Compound extends Entity implements IndexInterface{
	
	### INDEX
	
	public function getDoublesStorage(){
	}
	
	public function setDoublesStorage(Storage $storage){
	}
	
	public function search($value){
		$result = array();
		
		foreach($this->getIndexes() as $index){
			/* @var $index Interface */
			
			$result = array_merge($result, $index->search($value));
		}
		
		return $result;
	}
	
	public function insert($value, $rowId){
		foreach($this->getIndexes() as $index){
			/* @var $index Interface */
			
			$index->insert($value, $rowId);
		}
	}
	
	public function remove($value, $rowId){
		foreach($this->getIndexes() as $index){
			/* @var $index Interface */
			
			$index->remove($value, $rowId);
		}
	}
	
	### COMPOUND
	
	private $indexes = array();
	
	public function getIndexes(){
		return $this->indexes;
	}
	
	public function clearIndexes(){
		$this->indexes = array();
	}
	
	public function addIndex(IndexInterface $index){
		$this->indexes[] = $index;
	}
}
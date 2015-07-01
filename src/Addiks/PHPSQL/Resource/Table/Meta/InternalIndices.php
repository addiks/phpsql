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

namespace Addiks\Database\Resource\Table\Meta;

use Addiks\Common\Tool\CustomIterator;

use Addiks\Analyser\Tool\TokenIterator;

use Addiks\Protocol\Entity\Exception\Error;

use Addiks\Common\Resource;

use Addiks\Database\Entity\Index\IndexInterface;

use Addiks\Common\Resource;

class InternalIndices extends Resource implements IndexInterface{
	
	public function __construct($tableName, $schemaId = null){
		
		$tableNameParts = explode("__", $tableName);
		
		if(count($tableNameParts)!==3){
			throw new Error("Invalid identifier '{$tableName}' for index-dump!");
		}
		
		list($schemaId, $tableName, $indexId) = $tableNameParts;
		
		/* @var $index Index */
		$this->factorize($index, [$indexId, $tableName, $schemaId]);
		
		$this->index = $index;
		$this->schemaId = $schemaId;
		$this->tableName = $tableName;
	}
	
	private $index;
	
	public function getIndex(){
		return $this->index;
	}
	
	private $schemaId;
	
	public function getDBSchemaId(){
		return $this->schemaId;
	}
	
	public function getDBSchema(){
		
	}
	
	private $tableName;
	
	public function getTableName(){
		return $this->tableName;
	}
	
	public function getTableId(){
		
	}
	
	public function addColumnDefinition(ColumnDefinition $columnDefinition){
		
	}
	
	private $tableSchema;
	
	public function getTableSchema(){
		if(is_null($this->tableSchema)){
			
			/* @var $tableSchema Indicies */
			$this->factorize($tableSchema);
			
			/* @var $index Index */
			$index = $this->getIndex();
			
			$tableSchema->setKeyLength($index->getIndexBackend()->getKeyLength());
			
			$this->tableSchema = $tableSchema;
		}
		return $this->tableSchema;
	}
	
	private $iterator;
	
	public function getIterator(){
		
		if(is_null($this->iterator)){
			
			/* @var $index Index */
			$index = $this->getIndex();
			
			$indexBackend = $index->getIndexBackend();
			
			switch(true){
					
				default:
				case $indexBackend instanceof BTree:
					$nodeIterator = $indexBackend->getNodeIterator();
					$this->iterator = new CustomIterator($nodeIterator, [
						'valid' => function($valid) use($nodeIterator){
							#var_dump($nodeIterator->current());
							return $valid && !is_null($nodeIterator->current());
						},
						'current' => function($node) use($nodeIterator){
							/* @var $node Node */
						
							if(is_null($node)){
								throw new Error("Invalid node given from btree node-iterator!");
							}
					
							$row = array();
							foreach($node->getIterator() as $key => $value){
									
								$row[] = $value[0];
								$row[] = $value[1];
								$row[] = $value[2];
							}
					
							return $row;
						}
					]);
			
					break;
			}
		}
		return $this->iterator;
	}
	
	public function getCellData($rowId, $columnId){
		
	}
	
	public function setCellData($rowId, $columnId, $data){
		
	}
	
	public function getRowData($rowId=null){
		
	}
	
	public function setRowData($rowId, array $rowData){
		
	}
	
	public function addRowData(array $rowData){
		
	}
	
	public function removeRow($rowId){
		
	}
	
	public function getRowExists($rowId = null){
		
	}
	
	public function count(){
		
	}
	
	public function seek($rowId){
		
	}
	
	public function convertStringRowToDataRow(array $row){
		return $row;
	}
	
	public function convertDataRowToStringRow(array $row){
		return $row;
	}
	
}
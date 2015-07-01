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

namespace Addiks\Database\Entity\Result\Specifier;

use Addiks\Database\Entity\Job\Part\Value;

use Addiks\Common\Entity;

class Column extends Entity{
	
	private $name;
	
	public function getName(){
		return $this->name;
	}
	
	public function setName($name){
		$this->name = (string)$name;
	}
	
	private $schemaSource;
	
	public function setSchemaSourceColumn(Column $column){
		$this->schemaSource = $column;
	}
	
	public function setSchemaSourceTable(Table $table){
		$this->schemaSource = $table;
	}
	
	public function setSchemaSourceJoker(){
		$this->schemaSource = '*';
	}
	
	public function setSchemaSourceValue(Value $value){
		$this->schemaSource = $value;
	}
	
	public function getSchemaSource(){
		return $this->schemaSource;
	}
	
	/**
	 * If true and $schemaSource is a table-specifier, it adds all columns from table to result.
	 * $schemaSource has to be set.
	 * (Works like "SELECT * FROM someTable".)
	 * @var bool
	 */
	private $isAllColumnsFromTable = false;
	
	public function setIsAllColumnsFromTable($bool){
		$this->isAllColumnsFromTable = (bool)$bool;
	}
	
	public function getIsAllColumnsFromTable(){
		return $this->isAllColumnsFromTable;
	}
	
	### FOR EXPLICIT DATA-TYPE DECLARATION
	
	private $schemaSourceDataTypeLength;
	
	public function setSchemaSourceDataTypeLength($length){
		$this->schemaSourceDataTypeLength = (int)$length;
	}
	
	public function getSchemaSourceDataTypeLength(){
		return $this->schemaSourceDataTypeLength;
	}
	
	private $schemaSourceDataTypeSecondaryLength;
	
	public function setSchemaSourceDataTypeSecondaryLength($length){
		$this->schemaSourceDataTypeSecondaryLength = (int)$length;
	}
	
	public function getSchemaSourceDataTypeSecondaryLength(){
		return $this->schemaSourceDataTypeSecondaryLength;
	}
}
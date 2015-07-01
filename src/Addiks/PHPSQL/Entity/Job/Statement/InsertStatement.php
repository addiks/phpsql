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

namespace Addiks\Database\Entity\Job\Statement;

use Addiks\Database\Entity\Job\Insert\DataChange;

use Addiks\Database\Value\Specifier\ColumnSpecifier;

use Addiks\Database\Value\Specifier\TableSpecifier;

use Addiks\Database\Entity\Job\Statement;
use Addiks\Database\Service\Executor\InsertExecutor;

/**
 * 
 * @Addiks\Statement(executorClass="InsertExecutor")
 * @author gerrit
 *
 */
class InsertStatement extends Statement{

	private $table;
	
	public function setTable(TableSpecifier $table){
		$this->table = $table;
	}
	
	public function getTable(){
		return $this->table;
	}
	
	private $doIgnoreErrors = false;
	
	public function setDoIgnoreErrors($bool){
		$this->doIgnoreErrors = (bool)$bool;
	}
	
	public function getDoIgnoreErrors(){
		return $this->doIgnoreErrors;
	}
	
	private $priority;
	
	public function setPriority($priority){
		$this->priority = $priority;
	}
	
	public function getPriority(){
		return $this->priority;
	}
	
	private $columns = array();
	
	public function getColumns(){
		return $this->columns;
	}
	
	public function addColumnSelection(ColumnSpecifier $column){
		$this->columns[] = $column;
	}
	
	public function addColumnSetValue(DataChange $dataChange){
		$this->columns[] = $dataChange;
	}
	
	private $dataSource;
	
	public function getDataSource(){
		return $this->dataSource;
	}
	
	public function setDataSourceSelect(SelectStatement $selectJob){
		$this->dataSource = $selectJob;
	}
	
	public function addDataSourceValuesRow(array $row){
		if(!is_array($this->dataSource)){
			$this->dataSource = array();
		}
		$this->dataSource[] = $row;
	}
	
	private $onUpdateDataChanges = array();
	
	public function addOnDuplicateDataChange(DataChange $dataChange){
		$this->onUpdateDataChanges[] = $dataChange;
	}
	
	public function getOnDuplicateDataChanges(){
		return $this->onUpdateDataChanges;
	}
	
	/**
	 * @see Addiks\Database.Statement::getIsValid()
	 */
	public function validate(){

		if(is_array($this->getDataSource())){
			$columnCount = count($this->getColumns());
			foreach($this->getDataSource() as $rowIndex => $row){
				$rowIndex++;
				if(count($row) !== $columnCount){
					throw new InvalidArgument("Column-count in row #{$rowIndex} does not match column-count in column-specifier!");
				}
			}
		}
		
		parent::validate();
	}
}
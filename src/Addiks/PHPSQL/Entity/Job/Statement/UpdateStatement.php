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

use Addiks\Database\Entity\Exception\MalformedSql;
use Addiks\Database\Value\Enum\Sql\SqlToken;

use Addiks\Database\Entity\Job\Statement;
use Addiks\Database\Service\Executor\UpdateExecutor;

/**
 * 
 * @Addiks\Statement(executorClass="UpdateExecutor")
 * @author gerrit
 *
 */
class UpdateStatement extends Statement{

	private $tables = array();
	
	public function addTable(Table $table){
		$this->tables[] = $table;
	}
	
	public function getTables(){
		return $this->tables;
	}
	
	private $dataChanges = array();
	
	public function addDataChange(DataChange $change){
		$this->dataChanges[] = $change;
	}
	
	public function getDataChanges(){
		return $this->dataChanges;
	}
	
	private $condition;
	
	public function setCondition(Value $condition){
		$this->condition = $condition;
	}
	
	public function getCondition(){
		return $this->condition;
	}
	
	private $orderColumn;
	
	public function setOrderColumn(Column $column){
		$this->orderColumn = $column;
	}
	
	public function getOrderColumn(){
		return $this->orderColumn;
	}
	
	private $orderDirection;
	
	public function setOrderDirection($direction){
		switch($direction){
			case SqlToken::T_ASC():
			case SqlToken::T_DESC():
				$this->orderDirection = $direction;
				break;
				
			default:
				throw new MalformedSql("Invalid order-direction given to update job!", tokens);
		}
	}
	
	public function getOrderDirection(){
		if(is_null($this->orderDirection)){
			$this->orderDirection = SqlToken::T_ASC();
		}
		return $this->orderDirection;
	}
	
	private $limitOffset = 0;
	
	public function setLimitOffset($offset){
		$this->limitOffset = $offset;
	}
	
	public function getLimitOffset(){
		return $this->limitOffset;
	}
	
	private $limitRowCount;
	
	public function setLimitRowCount($count){
		$this->limitRowCount = (int)$count;
	}
	
	public function getLimitRowCount(){
		return $this->limitRowCount;
	}
	
	private $isLowPriority = false;
	
	public function setIsLowPriority($bool){
		$this->isLowPriority = (bool)$bool;
	}
	
	public function getIsLowPriority(){
		return $this->isLowPriority;
	}
	
	private $doIgnoreErrors = false;
	
	public function setDoIgnoreErrors($bool){
		$this->doIgnoreErrors = (bool)$bool;
	}
	
	public function getDoIgnoreErrors(){
		return $this->doIgnoreErrors;
	}
	
	public function getResultSpecifier(){
	}
	
}
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

namespace Addiks\Database\Entity\Job\Part;

use Addiks\Database\Value\Enum\Sql\ForeignKey\ReferenceOption;

use Addiks\Database\Value\Enum\Sql\ForeignKey\MatchType;

use Addiks\Database\Value\Specifier\ColumnSpecifier;

use Addiks\Database\Value\Enum\Sql\IndexType;

use Addiks\Database\Entity\Job\Part;

class Index extends Part{
	
	public function __construct(){
		parent::__construct();
		$this->setType(IndexType::BTREE());
	}
	
	private $name;
	
	public function setName($name){
		$this->name = $name;
	}
	
	public function getName(){
		if(is_null($this->name)){
			return reset($this->getColumns())->getColumn();
		}
		return $this->name;
	}
	
	private $isPrimary = false;
	
	public function setIsPrimary($bool){
		$this->isPrimary = (bool)$bool;
		if($this->getIsPrimary()){
			$this->setIsUnique(true);
		}
	}
	
	public function getIsPrimary(){
		return $this->isPrimary;
	}
	
	private $isUnique = false;
	
	public function setIsUnique($bool){
		$this->isUnique = (bool)$bool;
		if(!$this->getIsUnique()){
			$this->setIsPrimary(false);
		}
	}
	
	public function getIsUnique(){
		return $this->isUnique;
	}
	
	private $isFullText = false;
	
	public function setIsFullText($bool){
		$this->isFullText = (bool)$bool;
	}
	
	public function getIsFullText(){
		return $this->isFullText;
	}
	
	private $isSpatial = false;
	
	public function setIsSpatial($bool){
		$this->isSpatial = (bool)$bool;
	}
	
	public function getIsSpatial(){
		return $this->isSpatial;
	}
	
	private $type;
	
	public function setType(IndexType $type){
		$this->type = $type;
	}
	
	public function getType(){
		return $this->type;
	}
	
	private $columns = array();
	
	public function addColumn(ColumnSpecifier $column){
		$this->columns[] = $column;
	}
	
	public function getColumns(){
		return $this->columns;
	}
	
	private $constraintSymbol;
	
	public function setContraintSymbol($symbol){
		$this->constraintSymbol = $symbol;
	}
	
	public function getConstraintSymbol(){
		return $this->constraintSymbol;
	}
	
	private $foreignKeys;
	
	public function addForeignKey(Column $column){
		$this->foreignKeys[] = $column;
	}
	
	public function getForeignKeys(){
		return $this->foreignKeys;
	}
	
	private $foreignKeyMatchType;
	
	public function setForeignKeyMatchType(MatchType $matchTypes){
		$this->foreignKeyMatchType = $matchType;
	}
	
	public function getForeignKeyMatchType(){
		if(is_null($this->foreignKeyMatchType)){
			$this->foreignKeyMatchType = MatchType::FULL();
		}
		return $this->foreignKeyMatchType;
	}
	
	private $foreignKeyOnDeleteReferenceOption;
	
	public function setForeignKeyOnDeleteReferenceOption(ReferenceOption $option){
		$this->foreignKeyOnDeleteReferenceOption = $option;
	}
	
	public function getForeignKeyOnDeleteReferenceOption(){
		if(is_null($this->foreignKeyOnDeleteReferenceOption)){
			$this->foreignKeyOnDeleteReferenceOption = ReferenceOption::RESTRICT();
		}
		return $this->foreignKeyOnDeleteReferenceOption;
	}
	
	private $foreignKeyOnUpdateReferenceOption;
	
	public function setForeignKeyOnUpdateReferenceOption(ReferenceOption $option){
		$this->foreignKeyOnUpdateReferenceOption = $option;
	}
	
	public function getForeignKeyOnUpdateReferenceOption(){
		if(is_null($this->foreignKeyOnUpdateReferenceOption)){
			$this->foreignKeyOnUpdateReferenceOption = ReferenceOption::RESTRICT();
		}
		return $this->foreignKeyOnUpdateReferenceOption;
	}
	
}
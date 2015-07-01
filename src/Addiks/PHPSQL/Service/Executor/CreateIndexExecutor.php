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

namespace Addiks\Database\Service\Executor;

use Addiks\Database\Value\Enum\Page\Index\Type;

use Addiks\Database\Entity\Exception\Conflict;

use Addiks\Database\Value\Specifier\ColumnSpecifier;

use Addiks\Database\Value\Enum\Page\Index\Engine;

use Addiks\Database\Entity\TableSchema;

use Addiks\Database\Resource\Table;

use Addiks\Database\Entity\Page\Schema\Index;

use Addiks\Database\Service\Executor;

use Addiks\Database\Entity\Result\Temporary;

use Addiks\Database\Resource\Database;

class CreateIndexExecutor extends Executor{
	
	public function executeConcreteJob($statement, array $parameters = array()){
		/* @var $statement Index */
		
		/* @var $databaseResource Database */
		$this->factorize($databaseResource);
		
		/* @var $tableSpecifier TableSpecifier */
		$tableSpecifier = $statement->getTable();
		
		/* @var $schema Schema */
		$schema = $databaseResource->getSchema($tableSpecifier->getDatabase());
		
		if(!$schema->tableExists($tableSpecifier->getTable())){
			throw new Conflict("Table '{$tableSpecifier}' does not exist!");
		}
		
		### WRITE INDEX PAGE
		
		/* @var $indexPage Index */
		$this->factorize($indexPage);
		
		/* @var $tableResource Table */
		$this->factorize($tableResource, [$tableSpecifier->getTable(), $tableSpecifier->getDatabase()]);
		
		/* @var $tableSchema TableSchema */
		$tableSchema = $tableResource->getTableSchema();
		
		$indexPage->setName($statement->getName());
		$indexPage->setEngine(Engine::factory($statement->getIndexType()->getName()));
		
		$columnIds = array();
		$keyLength = 0;
		foreach($statement->getColumns() as $columnDataset){
			
			$columnSpecifier = $columnDataset['column'];
			/* @var $columnSpecifier Column */
			
			$columnId = $tableSchema->getColumnIndex($columnSpecifier->getColumn());
			
			if(is_null($columnId)){
				throw new Conflict("Cannot create index for unknown column '{$columnSpecifier->getColumn()}'!");
			}
			
			if(!is_null($columnDataset['length'])){
				$keyLength += (int)$columnDataset['length'];
			}else{
				$keyLength += $tableSchema->getColumn($columnId)->getLength();
			}
			
			$columnIds[] = $columnId;
		}
		
		$indexPage->setColumns($columnIds);
		$indexPage->setKeyLength($keyLength);
		
		if($statement->getIsPrimary()){
			$indexPage->setType(Type::PRIMARY());
			
		}else if($statement->getIsUnique()){
			$indexPage->setType(Type::UNIQUE());
			
		}else{
			$indexPage->setType(Type::INDEX());
		}
		
		$tableSchema->addIndexPage($indexPage);
		
		### PHSICALLY BUILD INDEX
		
		/* @var $indexResource Index */
		$this->factorize($indexResource, [$indexPage->getName(), $tableSpecifier->getTable(), $tableSpecifier->getDatabase()]);
		
		foreach($tableResource->getIterator() as $rowId => $row){
			
			$indexResource->insert($row, $rowId);
		}
	}
}
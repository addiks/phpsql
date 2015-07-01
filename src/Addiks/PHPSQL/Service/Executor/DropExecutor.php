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

use Addiks\Database\Service\Executor;

use Addiks\Database\Entity\Result\Temporary;

use Addiks\Database\Resource\Database;

class DropExecutor extends Executor{
	
	protected function executeConcreteJob($statement, array $parameters=array()){
		/* @var $statement Drop */
		
		switch($statement->getType()){
			case Drop::TYPE_DATABASE:
				return $this->executeDropDatabase($statement, $parameters);
				
			case Drop::TYPE_TABLE:
				return $this->executeDropTable($statement, $parameters);
				
			case Drop::TYPE_VIEW:
				return $this->executeDropView($statement, $parameters);
		}
		
	}
	
	protected function executeDropDatabase(Drop $statement, array $parameters=array()){
		
		/* @var $databaseResource Database */
		$this->factorize($databaseResource);
		
		/* @var $databaseSchema Schema */
		$databaseSchema = $databaseResource->getSchema();
		
		/* @var $result Result */
		$this->factorize($result, ["SHOW_DATABASES"]);
		
		foreach($statement->getSubjects() as $subject){
			$databaseResource->removeSchema($subject);
		}
		
		### RESULT
		
		/* @var $result Temporary */
		$this->factorize($result);
		
		$result->setIsSuccess(true);
		
		foreach($statement->getSubjects() as $subject){
			if($databaseResource->schemaExists($subject)){
				$result->setIsSuccess(false);
				break;
			}
		}
		
		return $result;
	}
	
	protected function executeDropTable(Drop $statement, array $parameters=array()){
		
		/* @var $databaseResource Database */
		$this->factorize($databaseResource);
		
		
		foreach($statement->getSubjects() as $tableName){
			$databaseResource->dropTable($tableName);
		}
		
		$databaseSchema = $databaseResource->getSchema();
		
		/* @var $result Temporary */
		$this->factorize($result);
		
		$result->setIsSuccess(!$databaseSchema->tableExists($tableName));
		
		return $result;
	}
	
	protected function executeDropView(Drop $statement, array $parameters=array()){
		
		/* @var $result Temporary */
		$this->factorize($result);
		
		return $result;
	}
	
}
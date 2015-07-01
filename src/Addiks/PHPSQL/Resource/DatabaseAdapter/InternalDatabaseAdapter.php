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

namespace Addiks\Database\Resource\DatabaseAdapter;

use Addiks\Database\Entity\TableSchema;

use Addiks\Database\Entity\Schema;

use Addiks\Database\Value\Database\Dsn\Internal;

use Addiks\Common\Tool\ClassAnalyzer;

use Addiks\Database\Service\Executor;

use Addiks\Database\Tool\SQLTokenIterator;
use Addiks\Database\Service\ValueResolver;
use Addiks\Database\Service\SqlParser;
use Addiks\Database\Entity\Result\Temporary;
use Addiks\Database\Entity\Storage;
use Addiks\Database\Entity\Job\Statement;

use Addiks\Common\Value\Text\Annotation;
use Addiks\Common\Resource;

use Addiks\Protocol\Entity\Exception\Error;
use Addiks\Database\Resource\Database\AbstractDatabase;

class InternalDatabaseAdapter extends AbstractDatabaseAdapter{

    public function getTypeName(){
        return 'internal';
    }
    
	use StoragesProxyTrait;
	
	const DATABASE_ID_DEFAULT = "default";
	const DATABASE_ID_META_MYSQL = "mysql";
	const DATABASE_ID_META_INFORMATION_SCHEMA = "information_schema";
	const DATABASE_ID_META_PERFORMANCE_SCHEMA = "performance_schema";
	const DATABASE_ID_META_INDICES = "indicies";
	
	private $currentDatabaseId = self::DATABASE_ID_DEFAULT;
	
	public function getCurrentlyUsedDatabaseId(){
		return $this->currentDatabaseId;
	}
	
	public function setCurrentlyUsedDatabaseId($schemaId){
		
		$pattern = Internal::PATTERN;
		if(!preg_match("/{$pattern}/is", $schemaId)){
			throw new InvalidArgument("Invalid database-id '{$schemaId}' given! (Does not match pattern '{$pattern}')");
		}
		
		if(!$this->schemaExists($schemaId)){
			throw new Conflict("Database '{$schemaId}' does not exist!");
		}
		
		$this->currentDatabaseId = $schemaId;
		
		return true;
	}
	
	public function query($statementString, array $parameters=array(), SQLTokenIterator $tokens = null){

	#	$this->log($statementString, $parameters);
		
		if($this->getIsStatementLogActive()){
			$this->logQuery($statementString);
		}
		
		$result = new Temporary();
			
		try{
			
			/* @var $sqlParser SqlParser */
			$this->factorize($sqlParser);
			
			/* @var $valueResolver ValueResolver */
			$this->factorize($valueResolver);
			
			$valueResolver->setStatementParameters($parameters);
			
			if(is_null($tokens)){
				$tokens = new SQLTokenIterator($statementString);
			}
			
			$jobs = $sqlParser->convertSqlToJob($tokens);
			
			foreach($jobs as $statement){
				/* @var $statement Statement */
				
				$result = $this->queryStatement($statement, $parameters);
			}
			
		}catch(Conflict $exception){
			
			print($exception);
				
			throw $exception;
			
		}catch(MalformedSql $exception){
			
			print($exception);
				
			throw $exception;
		}
		
		return $result;
	}
	
	public function queryStatement(Statement $statement, array $parameters = array()){
		
		if($this->getIsStatementLogActive()){
			$this->logStatement($statement);
		}
		
		$reflection = new \ReflectionClass($statement);
			
		/* @var $analyzer ClassAnalyzer */
		$analyzer = ClassAnalyzer::getInstanceFor($reflection->getName(), $reflection->getFileName());
			
		/* @var $annotation Annotation */
		$annotation = current($analyzer->getClassAnnotation('Addiks\\\\Statement'));
		
		if(!$annotation instanceof Annotation){
			throw new Error("Missing annotation 'Addiks\\Statement' in class '{$reflection->getName()}'!");
		}
		
		if(!isset($annotation['executorClass'])){
			throw new Error("Missing attribute 'executorClass' on annotation 'Addiks\\Statement' in class '{$reflection->getName()}'!");
		}
		
		$executorClass = $annotation['executorClass'];
		
		$useStatements = $analyzer->getUseStatements();
		
		if($executorClass[0] !== "\\"){
			if(isset($useStatements[$executorClass])){
				$executorClass = $useStatements[$executorClass];
				
			}else{
				$executorNamespace = ClassAnalyzer::getNamespaceFromFile($reflection->getFileName());
				$executorClass = "{$executorNamespace}\\{$executorClass}";
			}
			
			if($executorClass[0] !== "\\"){
				$executorClass = "\\{$executorClass}";
			}
		}
		
		if(is_null($executorClass)){
			throw new Error("No executor class defined for statement '{$reflection->getName()}'!");
		}
			
		/* @var $executor Executor */
		$executor = $this->factory($executorClass);
			
		$result = $executor->executeJob($statement, $parameters);
		
		return $result;
	}
	
	### SCHEMATA
	
	/**
	 * Gets the schema for a database.
	 * The schema contains information about what tables/views/... are present.
	 *
	 * @param string $schemaId
	 * @throws \Addiks\Protocol\Entity\Exception\Error
	 * @return Schema
	 */
	public function getSchema($schemaId = null){
		
		if(is_null($schemaId)){
			$schemaId = $this->getCurrentlyUsedDatabaseId();
		}
		
		if(!$this->schemaExists(self::DATABASE_ID_DEFAULT)){
			$this->createSchema(self::DATABASE_ID_DEFAULT);
		}
		
		$pattern = Internal::PATTERN;
		if(!preg_match("/{$pattern}/is", $schemaId)){
			throw new InvalidArgument("Invalid database-id '{$schemaId}' given! (Does not match pattern '{$pattern}')");
		}
		
		switch($schemaId){
			case self::DATABASE_ID_META_INDICES:
				/* @var $schema Indicies */
				$this->factorize($schema);
				break;
				
			case self::DATABASE_ID_META_INFORMATION_SCHEMA:
				/* @var $schema InformationSchema */
				$this->factorize($schema);
				break;
				
			default:
				/* @var $schema Schema */
				$this->factorize($schema, [$this->getDatabaseSchemaStorage($schemaId)]);
				break;
				
		}
		
		return $schema;
	}
	
	public function isMetaSchema($schemaId){
		return in_array($schemaId, [
			self::DATABASE_ID_META_INDICES,
			self::DATABASE_ID_META_INFORMATION_SCHEMA,
			self::DATABASE_ID_META_MYSQL,
			self::DATABASE_ID_META_PERFORMANCE_SCHEMA,
		]);
	}
	
	public function schemaExists($schemaId){
		
		$pattern = Internal::PATTERN;
		if(!preg_match("/{$pattern}/is", $schemaId)){
			throw new InvalidArgument("Invalid database-id '{$schemaId}' given! (Does not match pattern '{$pattern}')");
		}
		
		if($this->isMetaSchema($schemaId)){
			return true;
		}
		
		/* @var $storages \Addiks\Database\Resource\Storages */
		$this->factorize($storages);
		
		return $storages->storageExists("Databases/{$schemaId}.schema");
	}
	
	public function createSchema($schemaId){
		
		$pattern = Internal::PATTERN;
		if(!preg_match("/{$pattern}/is", $schemaId)){
			throw new InvalidArgument("Invalid database-id '{$schemaId}' given! (Does not match pattern '{$pattern}')");
		}
		
		if($this->schemaExists($schemaId)){
			throw new Conflict("Database '{$schemaId}' already exist!");
		}
		
		/* @var $storages \Addiks\Database\Resource\Storages */
		$this->factorize($storages);
		
		/* @var $schema Schema */
		$this->factorize($schema, [$this->getDatabaseSchemaStorage($schemaId)]);
		
		$schema->setId($schemaId);
	}
	
	public function removeSchema($schemaId){
		
		$pattern = Internal::PATTERN;
		if(!preg_match("/{$pattern}/is", $schemaId)){
			throw new InvalidArgument("Invalid database-id '{$schemaId}' given! (Does not match pattern '{$pattern}')");
		}
		
		if($this->isMetaSchema($schemaId)){
			throw new Conflict("Cannot remove or modify meta-database '{$schemaId}'!");
		}
		
		/* @var $storages \Addiks\Database\Resource\Storages */
		$this->factorize($storages);
		
		$storages->removeStorage($this->getDatabaseSchemaStorage($schemaId));
	}
	
	public function listSchemas(){
		
		if(!$this->schemaExists(self::DATABASE_ID_DEFAULT)){
			$this->createSchema(self::DATABASE_ID_DEFAULT);
		}
		
		/* @var $storages \Addiks\Database\Resource\Storages */
		$this->factorize($storages);
		
		$iterator = $storages->getStoreIterator("Databases");
		
		if(is_null($iterator)){
			return array();
		}
		
		$result = array();
		foreach($iterator as $schemaName => $schemaIterator){
			/* @var $schemaIterator CustomIterator */
			
			if(substr($schemaName, strlen($schemaName)-7)==='.schema'){
				$schemaName = substr($schemaName, 0, strlen($schemaName)-7);
			}
			
			$result[] = (string)$schemaName;
		}
		
		$result[] = self::DATABASE_ID_META_INDICES;
		$result[] = self::DATABASE_ID_META_INFORMATION_SCHEMA;
	#	$result[] = self::DATABASE_ID_META_PERFORMANCE_SCHEMA;
	#	$result[] = self::DATABASE_ID_META_MYSQL;
		
		$result = array_unique($result);
		
		return $result;
	}
	
	public function getTableSchema($tableName, $schemaId = null){
		
		if(is_null($schemaId)){
			$schemaId = $this->getCurrentlyUsedDatabaseId();
		}
		
		$schema = $this->getSchema($schemaId);
		
		if(is_int($tableName)){
			$tableIndex = $tableName;
			
		}else{
			$tableIndex = $schema->getTableIndex((string)$tableName);
		}
		
		if(is_null($tableIndex)){
			return null;
		}
		
		$tableSchemaStorage = $this->getTableSchemaStorage((string)$tableName, $schemaId);
		$indexSchemaStorage = $this->getTableIndexStorage((string)$tableName, $schemaId);

		switch($schemaId){
			
			case self::DATABASE_ID_META_INFORMATION_SCHEMA:
				/* @var $tableSchema InformationSchema */
				$this->factorize($tableSchema, [$tableSchemaStorage, $indexSchemaStorage, $tableName]);
				break;
			
			default:
				/* @var $tableSchema TableSchema */
				$this->factorize($tableSchema, [$tableSchemaStorage, $indexSchemaStorage]);
				break;
		}
		
		$tableSchema->setDatabaseSchema($schema);
		
		return $tableSchema;
	}
	
	public function dropTable($tableName, $schemaId = null){
		
		if(is_null($schemaId)){
			$schemaId = $this->getCurrentlyUsedDatabaseId();
		}
		
		/* @var $schema Schema */
		$schema = $this->getSchema($schemaId);
		
		if(!$schema->tableExists($tableName)){
			throw new Conflict("Table {$tableName} does not exist!");
		}
		
		$schema->unregisterTable($tableName);
		
		/* @var $storages \Addiks\Database\Resource\Storages */
		$this->factorize($storages);
		
		$storagePath = $this->getTableStoragePath($tableName, $schemaId);
		
		$storages->removeStorage($storagePath);
	}
	
	### VIEW
	
	public function getViewQuery($viewName, Schema $schema = null){
		
		if(is_null($schema)){
			$schema = $this->getSchema();
		}
		
		$viewIndex = $schema->getViewIndex($viewName);
		
		if(is_null($viewIndex)){
			return null;
		}
		
		/* @var $storages \Addiks\Database\Resource\Storages */
		$this->factorize($storages);
		
		$viewQueryStorage = $this->getViewDefinitionStorage($viewIndex, $schema->getId());
		
		return $viewQueryStorage->getData();
	}
	
	public function setViewQuery($query, $viewName, Schema $schema = null){
		
		if(is_null($schema)){
			$schema = $this->getSchema();
		}
		
		$viewIndex = $schema->getViewIndex($viewName);
		
		if(is_null($viewIndex)){
			$schema->registerView($viewName);
			$viewIndex = $schema->getViewIndex($viewName);
		}
		
		/* @var $storages \Addiks\Database\Resource\Storages */
		$this->factorize($storages);
		
		$viewQueryStorage = $this->getViewDefinitionStorage($viewIndex, $schema->getId());
		$viewQueryStorage->setData($query);
	}
	
	public function factorizeDsn($value){
		
		$value  = explode(":", $value);
		$driver = reset($value);
		$driver = ucwords(strtolower($driver));
		
		/* @var $metadata \Addiks\Common\Service\MetaDataCache */
		$this->factorize($metadata);
		
		foreach($metadata->getModuleClasses("Dsn_{$driver}") as $classId){
			
			return $this->factory($classId, [$dsn]);
		}
		
		throw new \InvalidArgumentException("Unknown DSN driver '{$driver}'!");
	}
	
	private $isStatementLogActive;
	
	public function setIsStatementLogActive($bool){
		$this->isStatementLogActive = (bool)$bool;
	}
	
	public function getIsStatementLogActive(){
		if(is_null($this->isStatementLogActive)){
			$this->isStatementLogActive = $this->isDevelopmentMode() || true;
		}
		return $this->isStatementLogActive;
	}

	protected function logQuery($statement){
	
		$logStorage = $this->getStorage("QueryLog");
	
		$date = date("Y-m-d H-i-s", time());
		
		fwrite($logStorage->getHandle(), "\n\n{$date}:\n" . $statement);
	}
	
	protected function logStatement(Statement $statement){
		
		$logStorage = $this->getStorage("StatementLog");

		$date = date("Y-m-d H-i-s", time());
		
		fwrite($logStorage->getHandle(), "\n\n{$date}:\n" . (string)$statement);
	}
	
}
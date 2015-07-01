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

namespace Addiks\Database\Resource;

use Addiks\Protocol\Entity\Exception\Error;

trait StoragesProxyTrait{
	
	### STORAGE GETTER
	
	protected function getDatabaseSchemaStorage($schemaId=null){
		$schemaId = $this->getDefaultSchemaId($schemaId);
		$storagePath = sprintf("Databases/%s/Schema", $schemaId);
		return $this->getStorages()->acquireStorage($storagePath);
	}
	
	protected function getTableStoragePath($tableName, $schemaId=null){
		$schemaId = $this->getDefaultSchemaId($schemaId);
		$storagePath = sprintf("Databases/%s/Tables/%s", $schemaId, $tableName);
		return $storagePath;
	}
	
	protected function getTableSchemaStorage($tableName, $schemaId=null){
		$schemaId = $this->getDefaultSchemaId($schemaId);
		$storagePath = sprintf("Databases/%s/Tables/%s/Schema", $schemaId, $tableName);
		return $this->getStorages()->acquireStorage($storagePath);
	}
	
	protected function getTableIndexStorage($tableName, $schemaId=null){
		$schemaId = $this->getDefaultSchemaId($schemaId);
		$storagePath = sprintf("Databases/%s/Tables/%s/IndexSchema", $schemaId, $tableName);
		return $this->getStorages()->acquireStorage($storagePath);
	}
	
	protected function getTableDeletedRowsStorage($tableName, $schemaId=null){
		$schemaId = $this->getDefaultSchemaId($schemaId);
		$storagePath = sprintf("Databases/%s/Tables/%s/DeletedRows", $schemaId, $tableName);
		return $this->getStorages()->acquireStorage($storagePath);
	}
	
	protected function getTableColumnDataStorage($dataIndex, $columnId, $tableName, $schemaId=null){
		$schemaId = $this->getDefaultSchemaId($schemaId);
		
		if(is_numeric($columnId)){
			throw new Error("Column-Name '{$columnId}' cannot be numeric!");
		}
		
		$storagePath = sprintf("Databases/%s/Tables/%s/ColumnData/%s/Data/%s", $schemaId, (string)$tableName, (string)$columnId, (string)$dataIndex);
		return $this->getStorages()->acquireStorage($storagePath);
	}
	
	protected function getTableColumnDataLastDataIndex($columnId, $tableName, $schemaId=null){
		$schemaId = $this->getDefaultSchemaId($schemaId);
		
		if(is_numeric($columnId)){
			throw new Error("Column-Name '{$columnId}' cannot be numeric!");
		}
		
		/* @var $storages \Addiks\Database\Resource\Storages */
		$this->factorize($storages);
		
		$storagePath = sprintf("Databases/%s/Tables/%s/ColumnData/%s/Data", $schemaId, (string)$tableName, (string)$columnId);
		
		$lastDataIndex = null;
		
		foreach($storages->getStoreIterator($storagePath) as $dataIndex => $columnDataStorage){
			
			if($lastDataIndex < $dataIndex){
				$lastDataIndex = $dataIndex;
			}
		}
		
		return $lastDataIndex;
	}
	
	protected function getTableAutoIncrementIdStorage($tableName, $schemaId=null){
		$schemaId = $this->getDefaultSchemaId($schemaId);
		$storagePath = sprintf("Databases/%s/Tables/%s/AutoIncrementId", $schemaId, $tableName);
		return $this->getStorages()->acquireStorage($storagePath);
	}
	
	protected function getTableColumnIndexStorage($indexName, $tableName, $schemaId=null){
		$schemaId = $this->getDefaultSchemaId($schemaId);
		$storagePath = sprintf("Databases/{$schemaId}/Tables/{$tableName}/Indices/{$indexName}", $schemaId, $tableName, $indexName);
		return $this->getStorages()->acquireStorage($storagePath);
	}
	
	protected function getTableColumnIndexDoublesStorage($indexName, $tableName, $schemaId=null){
		$schemaId = $this->getDefaultSchemaId($schemaId);
		$storagePath = sprintf("Databases/{$schemaId}/Tables/{$tableName}/IndicesDoubles/{$indexName}", $schemaId, $tableName, $indexName);
		return $this->getStorages()->acquireStorage($storagePath);
	}
	
	protected function getViewDefinitionStorage($viewId, $schemaId=null){
		$schemaId = $this->getDefaultSchemaId($schemaId);
		$storagePath = sprintf("Databases/%s/Views/%s/Definition", $schemaId, $viewId);
		return $this->getStorages()->acquireStorage($storagePath);
	}
	
	protected function getResultSchemataStorage($resultId, $schemaId=null){
		$schemaId = $this->getDefaultSchemaId($schemaId);
		$storagePath = sprintf("Databases/%s/Result/%s/Schema", $schemaId, $resultId);
		return $this->getStorages()->acquireStorage($storagePath);
	}
	
	protected function getResultStatementStorage($resultId, $schemaId=null){
		$schemaId = $this->getDefaultSchemaId($schemaId);
		$storagePath = sprintf("Databases/%s/Result/%s/Statement", $schemaId, $resultId);
		return $this->getStorages()->acquireStorage($storagePath);
	}
	
	protected function getResultSourceColumnStorage($alias, $resultId, $schemaId=null){
		$schemaId = $this->getDefaultSchemaId($schemaId);
		$storagePath = sprintf("Databases/%s/Result/%s/SourceColumn/%s", $schemaId, $resultId, $alias);
		return $this->getStorages()->acquireStorage($storagePath);
	}
	
	protected function getResultStructureIndexStorage($resultId, $schemaId=null){
		$schemaId = $this->getDefaultSchemaId($schemaId);
		$storagePath = sprintf("Databases/%s/Result/%s/StructureIndex", $schemaId, $resultId);
		return $this->getStorages()->acquireStorage($storagePath);
	}
	
	### HELPER
	
	private function getDefaultSchemaId($schemaId = null){
	
		if(!is_null($schemaId)){
			return $schemaId;
		}
	
		/* @var $database Database */
		$this->factorize($database);
	
		return $database->getCurrentlyUsedDatabaseId();
	}
	
	private function getStorages(){
	
		/* @var $storages \Addiks\Database\Resource\Storages */
		$this->factorize($storages);
	
		return $storages;
	}
	
}
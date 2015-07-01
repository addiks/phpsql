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

namespace Addiks\Database\Resource\Table;

use Addiks\Database\Value\Enum\Page\Column\DataType;

use Addiks\Database\Entity\Storage;

use Addiks\Database\Entity\TableSchema;

use Addiks\Database\Entity\ColumnData;

use Addiks\Database\Entity\Job\Part\Value;

use Addiks\Database\Service\DataConverter;

use Addiks\Database\Service\ValueResolver;

use Addiks\Database\Entity\Page\Column;

use Addiks\Database\Entity\Job\Part\ColumnDefinition;

use Addiks\Database\Resource\Database;

use Addiks\Database\Resource\TableInterface;

use Addiks\Database\Service\BinaryConverterTrait;

use Addiks\Database\Resource\StoragesProxyTrait;

use Addiks\Common\Resource;

use Addiks\Protocol\Entity\Exception\Error;

use Addiks\Common\Tool\CustomIterator;

/**
 * 
 * @author gerrit
 * @Addiks\Singleton(negated=true)
 */
class Internal extends Resource implements TableInterface{

	use StoragesProxyTrait;
	use BinaryConverterTrait;

	public function __construct($tableName, $schemaId = null){

		/* @var $databaseResource Database */
		$this->factorize($databaseResource);

		if(is_null($schemaId)){
			$schemaId = $databaseResource->getCurrentlyUsedDatabaseId();
		}

		$schema = $databaseResource->getSchema($schemaId);

		$this->dbSchemaId = $schemaId;
		$this->dbSchema = $schema;
		$this->tableSchema = $databaseResource->getTableSchema($tableName, $schemaId);
		$this->tableId = $schema->getTableIndex($tableName);
		$this->tableName = $tableName;
	}

	private $dbSchemaId;

	public function getDBSchemaId(){
		return $this->dbSchemaId;
	}

	private $dbSchema;

	public function getDBSchema(){
		return $this->dbSchema;
	}

	private $tableName;

	public function getTableName(){
		return $this->tableName;
	}

	private $tableId;

	public function getTableId(){
		return $this->tableId;
	}

	private $tableSchema;

	/**
	 *
	 * @return TableSchema
	 */
	public function getTableSchema(){
		return $this->tableSchema;
	}
	
	public function addColumnDefinition(ColumnDefinition $columnDefinition){
	
		/* @var $tableSchema TableSchema */
		$tableSchema = $this->getTableSchema();
		
		if(!is_null($tableSchema->getColumnIndex($columnDefinition->getName()))){
			throw new Conflict("Column '{$columnDefinition->getName()}' already exist!");
		}
		
		/* @var $valueResolver ValueResolver */
		$this->factorize($valueResolver);
		
		$columnPage = new Column();
	
		$columnPage->setName($columnDefinition->getName());
	
		$columnPage->setDataType($columnDefinition->getDataType());
	
		if(!is_null($columnDefinition->getDataTypeLength())){
			$columnPage->setLength($columnDefinition->getDataTypeLength());
		}
		
		if(!is_null($columnDefinition->getDataTypeSecondLength())){
			$columnPage->setSecondLength($columnDefinition->getDataTypeSecondLength());
		}
		
		$flags = 0;
	
		if($columnDefinition->getIsAutoIncrement()){
			$flags = $flags ^ Column::EXTRA_AUTO_INCREMENT;
		}
	
		if(!$columnDefinition->getIsNullable()){
			$flags = $flags ^ Column::EXTRA_NOT_NULL;
		}
	
		if($columnDefinition->getIsPrimaryKey()){
			$flags = $flags ^ Column::EXTRA_PRIMARY_KEY;
		}
			
		if($columnDefinition->getIsUnique()){
			$flags = $flags ^ Column::EXTRA_UNIQUE_KEY;
		}
	
		if($columnDefinition->getIsUnsigned()){
			$flags = $flags ^ Column::EXTRA_UNSIGNED;
		}
	
		if(false){
			$flags = $flags ^ Column::EXTRA_ZEROFILL;
		}
		
		$columnPage->setExtraFlags($flags);
	
		#$columnPage->setFKColumnIndex($index);
		#$columnPage->setFKTableIndex($index);
		
		/* @var $defaultValue Value */
		$defaultValue = $columnDefinition->getDefaultValue();
		
		/* @var $dataConverter DataConverter */
		$this->factorize($dataConverter);
		
		if(!is_null($defaultValue)){
			$defaultValueData = $valueResolver->resolveValue($defaultValue);
			$defaultValueData = $dataConverter->convertStringToBinary($defaultValueData, $columnPage->getDataType());
		}else{
			$defaultValueData = null;
		}
		
		$columnIndex = $this->getTableSchema()->addColumnPage($columnPage);
		
		$rowCount = $this->count();
	
		for($rowId=0;$rowId<$rowCount;$rowId++){
			
			/* @var $columnData ColumnData */
			$columnData = $this->getColumnDataByRowIndex($rowId, $columnIndex);
			
			$columnDataRowId = $rowId % $this->getRowsPerColumnData($columnIndex);
			
			$columnData->setCellData($columnDataRowId, $defaultValueData);
		}
	
		$comment = $columnDefinition->getComment();
	}
	
	const BYTES_PER_DATAFILE = 131072; # = 128*1024;

	protected function getRowsPerColumnData($columnId){

		/* @var $columnSchemaPage Column */
		$columnSchemaPage = $this->getTableSchema()->getColumn($columnId);

		return ceil(self::BYTES_PER_DATAFILE / $columnSchemaPage->getCellSize());
	}

	private $columnDataCache = array();

	/**
	 *
	 * @param int $rowIndex
	 * @param int $columnId
	 * @return ColumnData
	 */
	public function getColumnDataByRowIndex($rowIndex, $columnId, &$columnDataIndex = 0){

		if(is_numeric($columnId)){
			$columnId = $this->getTableSchema()->getColumn($columnId)->getName();
		}

		if(!isset($this->columnDataCache[$columnId])){
			$this->columnDataCache[$columnId] = array();
		}

		$rowsPerColumnData = $this->getRowsPerColumnData($columnId);

		$columnDataIndex = floor($rowIndex / $rowsPerColumnData);

		if(!isset($this->columnDataCache[$columnId][$columnDataIndex])){

			/* @var $storages \Addiks\Database\Resource\Storages */
			$this->factorize($storages);

			/* @var $columnDataStorage \Addiks\Database\Entity\Storage */
			$columnDataStorage = $this->getTableColumnDataStorage($columnDataIndex, $columnId, $this->getTableName(), $this->dbSchemaId);

			/* @var $columnSchemaPage Column */
			$columnSchemaPage = $this->getTableSchema()->getColumn($columnId);

			/* @var $columnData ColumnData */
			$this->factorize($columnData, [$columnDataStorage, $columnSchemaPage]);

			if($columnDataStorage->getLength() <= 0){
				$columnData->preserveSpace($this->getRowsPerColumnData($columnId));
			}

			$this->columnDataCache[$columnId][$columnDataIndex] = $columnData;
		}

		return $this->columnDataCache[$columnId][$columnDataIndex];
	}

	### WORK WITH DATA

	public function getCellData($rowId, $columnId){

		/* @var $columnData ColumnData */
		$columnData = $this->getColumnDataByRowIndex($rowId, $columnId);

		$columnData->getCellData($rowId);
	}

	public function setCellData($rowId, $columnId, $data){

		/* @var $columnData ColumnData */
		$columnData = $this->getColumnDataByRowIndex($rowId, $columnId);

		$columnData->setCellData($rowId, $data);
	}

	public function getRowExists($rowId = null){

		if(is_null($rowId)){
			$rowId = $this->getCurrentRowIndex();
		}

		if(is_null($rowId)){
			return false;
		}

		/* @var $tableSchema TableSchema */
		$tableSchema = $this->getTableSchema();
		
		foreach($tableSchema->getPrimaryKeyColumns() as $columnId => $columnPage){
			/* @var $columnPage Column */
			
			$columnName = $columnPage->getName();
			
			/* @var $columnData ColumnData */
			$columnData = $this->getColumnDataByRowIndex($rowId, $columnName, $columnDataIndex);
				
			$columnDataRowId = $rowId % $this->getRowsPerColumnData($columnId);
			
			if($columnData->count() < $columnDataRowId){
				return false;
			}
			
			if(!is_null($columnData->getCellData($columnDataRowId))){
				return true;
			}
		}
		
		return false;
	}

	public function getRowCount(){
		
		/* @var $tableSchema TableSchema */
		$tableSchema = $this->getTableSchema();
		
		foreach($tableSchema->getPrimaryKeyColumns() as $columnPage){
			/* @var $columnPage Column */
			
			$lastDataIndex = $this->getTableColumnDataLastDataIndex($columnPage->getName(), $this->getTableName(), $this->getDBSchemaId());
			
			if(is_null($lastDataIndex)){
				return 0;
			}
			
			/* @var $columnDataStorage Storage */
			$columnDataStorage = $this->getTableColumnDataStorage($lastDataIndex, $columnPage->getName(), $this->getTableName(), $this->getDBSchemaId());
			
			$columnSchemaPage = $tableSchema->getColumn(0);
			
			/* @var $columnData ColumnData */
			$this->factorize($columnData, [$columnDataStorage, $columnSchemaPage]);
			
			$lastColumnDataIndex = $columnData->count();
			
			$beforeLastColumnDataRowCount = $this->getRowsPerColumnData(0) * $lastDataIndex;
			
			$lastIndex = $lastColumnDataIndex + $beforeLastColumnDataRowCount;
			
			return $lastIndex +1 -$this->getDeletedRowsCount();
			
		}
		
		return 0;
	}
	
	public function getNamedRowData($rowId=null){
		
		if(is_null($rowId)){
			$rowId = $this->getCurrentRowIndex();
		}
		
		$rowData = $this->getRowData($rowId);
	
		$tableSchema = $this->getTableSchema();
	
		$namedRow = array();
	
		foreach($rowData as $columnId => $value){
	
			$namedRow[$tableSchema->getColumn($columnId)->getName()] = $value;
		}
	
		return $namedRow;
	}
	
	const ROWCACHE_SIZE = 256;
	
	private $rowCache = array();
	
	public function getRowData($rowId=null){

		if(is_null($rowId)){
			$rowId = $this->getCurrentRowIndex();
		}

		if(isset($this->rowCache[$rowId])){
			return $this->rowCache[$rowId];
		}
		
		/* @var $tableSchema TableSchema */
		$tableSchema = $this->getTableSchema();

		$rowData = array();

		foreach($tableSchema->getCachedColumnIds() as $columnId){
			/* @var $columnPage Column */

			/* @var $columnData ColumnData */
			$columnData = $this->getColumnDataByRowIndex($rowId, $columnId);
			
			$columnDataRowId = $rowId % $this->getRowsPerColumnData($columnId);
				
			$rowData[$columnId] = $columnData->getCellData($columnDataRowId);
		}
		
		if(count($this->rowCache) < self::ROWCACHE_SIZE){
			$this->rowCache[$rowId] = $rowData;
		}
		
		return $rowData;
	}

	public function setRowData($rowId, array $rowData){

		/* @var $tableSchema TableSchema */
		$tableSchema = $this->getTableSchema();

		foreach($rowData as $columnId => $data){

			/* @var $columnData ColumnData */
			$columnData = $this->getColumnDataByRowIndex($rowId, $columnId);

			$columnData->setCellData($rowId, $data);
		}
	}

	public function addRowData(array $rowData){

		$rowId = $this->popDeletedRowStack();
		
		if(is_null($rowId)){
			$rowId = $this->getRowCount();
		}
		
		foreach($rowData as $columnId => $data){

			/* @var $columnData ColumnData */
			$columnData = $this->getColumnDataByRowIndex($rowId, $columnId);

			$columnData->setCellData($rowId, $data);
		}

		return $rowId;
	}

	public function removeRow($rowId){
		
		/* @var $tableSchema TableSchema */
		$tableSchema = $this->getTableSchema();
		
		foreach($tableSchema->getCachedColumnIds() as $columnId){
			/* @var $columnPage Column */
			
			/* @var $columnData ColumnData */
			$columnData = $this->getColumnDataByRowIndex($rowId, $columnId);
			
			$columnDataRowId = $rowId % $this->getRowsPerColumnData($columnId);
			
			$columnData->removeCell($columnDataRowId);
		}
		
		$this->pushDeletedRowStack($rowId);
	}
	
	const DELETEDROWS_PAGE_SIZE = 16;
	
	protected function popDeletedRowStack(){
		
		/* @var $storage Storage */
		$storage = $this->getTableDeletedRowsStorage($this->getTableName(), $this->getDBSchemaId());
		
		$handle = $storage->getHandle();
		
		flock($handle, LOCK_EX);
		
		fseek($handle, 0, SEEK_END);
		
		if(ftell($handle)===0){
			return null;
		}
		
		fseek($handle, 0-self::DELETEDROWS_PAGE_SIZE, SEEK_CUR);
		$sizeAfterFetch = ftell($handle);
		
		$rowId = fread($handle, self::DELETEDROWS_PAGE_SIZE);
		
		ftruncate($handle, $sizeAfterFetch);
		
		flock($handle, LOCK_UN);
		
		$rowId = $this->strdec($rowId);
		return $rowId;
	}
	
	protected function pushDeletedRowStack($rowId){
		
		/* @var $storage Storage */
		$storage = $this->getTableDeletedRowsStorage($this->getTableName(), $this->getDBSchemaId());
		
		$handle = $storage->getHandle();
		
		$rowId = $this->decstr($rowId);
		$rowId = str_pad($rowId, self::DELETEDROWS_PAGE_SIZE, "\0", STR_PAD_LEFT);
		
		flock($handle, LOCK_EX);
		fseek($handle, 0, SEEK_END);
		fwrite($handle, $rowId);
		flock($handle, LOCK_UN);
	}
	
	protected function getDeletedRowsCount(){
		
		/* @var $storage Storage */
		$storage = $this->getTableDeletedRowsStorage($this->getTableName(), $this->getDBSchemaId());
		
		$handle = $storage->getHandle();
		
		flock($handle, LOCK_SH);
		fseek($handle, 0, SEEK_END);
		
		$count = ftell($handle) / self::DELETEDROWS_PAGE_SIZE;
		
		flock($handle, LOCK_UN);
		
		return $count;
	}
	
	private $iterator;

	private $currentRowIndex = 0;

	public function seek($rowId){
		$this->setCurrentRowIndex($rowId);
	}

	public function setCurrentRowIndex($rowId){
		
		if(is_null($rowId)){
			$this->currentRowIndex = null;
			return;
		}

		if(is_string($rowId)){
			$rowId = $this->strdec($rowId);
		}
		if(!is_int($rowId)){
			throw new Error("Row-id has to be integer!");
		}
		if(!$this->getRowExists($rowId)){
			throw new Error("Seek to non-existing row-id '{$rowId}'!");
		}

		$this->currentRowIndex = $rowId;
	}

	public function getCurrentRowIndex(){
		return $this->currentRowIndex;
	}

	public function getIterator(){

		if(is_null($this->iterator)){

			$tableResource = $this;

			$this->iterator = new CustomIterator(null, [
				'rewind' => function() use($tableResource){
					if($tableResource->count()>0){
						$tableResource->seek(0);
					}
				},
				'valid' => function() use($tableResource){
					return $tableResource->getRowExists();
				},
				'current' => function() use($tableResource){
					if(!$tableResource->getRowExists()){
						return null;
					}
					return $tableResource->getNamedRowData();
				},
				'key' => function() use($tableResource){
					return $tableResource->getCurrentRowIndex();
				},
				'next' => function() use($tableResource){
					$newRowId = $tableResource->getCurrentRowIndex()+1;
					if($tableResource->getRowExists($newRowId)){
						$tableResource->seek($newRowId);
					}else{
						$tableResource->seek(null);
					}
				}
			]);
		}

		return $this->iterator;
	}

	public function count(){
		return $this->getRowCount();
	}

	public function convertStringRowToDataRow(array $row){

		$tableSchema = $this->getTableSchema();

		/* @var $dataConverter DataConverter */
		$this->factorize($dataConverter);

		foreach($row as $columnId => &$value){

			if(is_null($value)){
				continue;
			}

			/* @var $columnPage Column */
			$columnPage = $tableSchema->getColumn($columnId);

			/* @var $dataType DataType */
			$dataType = $columnPage->getDataType();

			$value = $dataConverter->convertStringToBinary($value, $dataType);
		}

		return $row;
	}

	public function convertDataRowToStringRow(array $row){

		$tableSchema = $this->getTableSchema();

		/* @var $dataConverter DataConverter */
		$this->factorize($dataConverter);

		foreach($row as $columnId => &$value){

			if(is_null($value)){
				continue;
			}

			/* @var $columnPage Column */
			$columnPage = $tableSchema->getColumn($columnId);

			/* @var $dataType DataType */
			$dataType = $columnPage->getDataType();
				
			$value = $dataConverter->convertBinaryToString($value, $dataType);
		}

		return $row;
	}
}
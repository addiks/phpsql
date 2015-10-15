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

namespace Addiks\PHPSQL\Table;

use Iterator;
use ErrorException;
use InvalidArgumentException;
use Addiks\PHPSQL\Value\Enum\Page\Column\DataType;
use Addiks\PHPSQL\Table\TableSchema;
use Addiks\PHPSQL\Entity\ColumnData;
use Addiks\PHPSQL\Job\Part\Value;
use Addiks\PHPSQL\DataConverter;
use Addiks\PHPSQL\ValueResolver\ValueResolver;
use Addiks\PHPSQL\Job\Part\ColumnDefinition;
use Addiks\PHPSQL\Database\Database;
use Addiks\PHPSQL\BinaryConverterTrait;
use Addiks\PHPSQL\Iterators\CustomIterator;
use Addiks\PHPSQL\Filesystem\FilesystemInterface;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\Filesystem\FilePathes;
use Addiks\PHPSQL\Table\TableInterface;
use Addiks\PHPSQL\Iterators\UsesBinaryDataInterface;
use Addiks\PHPSQL\StatementExecutor\ExecutionContext;
use Addiks\PHPSQL\Index;
use Addiks\PHPSQL\Value\Enum\Page\Index\IndexEngine;
use Addiks\PHPSQL\Index\BTree;
use Addiks\PHPSQL\Index\HashTable;
use Addiks\PHPSQL\Filesystem\FileInterface;
use Addiks\PHPSQL\Column\ColumnSchema;

class Table implements Iterator, TableInterface, UsesBinaryDataInterface
{

    use BinaryConverterTrait;

    public function __construct(
        TableSchema $tableSchema,
        array $columnDatas,
        array $indicies,
        FileInterface $autoIncrementFile,
        FileInterface $deletedRowsFile,
        $valueResolver = null,
        $dataConverter = null
    ) {

        if (is_null($valueResolver)) {
            $valueResolver = new ValueResolver();
        }

        if (is_null($dataConverter)) {
            $dataConverter = new DataConverter();
        }

        $this->columnDatas = $columnDatas;
        $this->indicies = $indicies;
        $this->valueResolver = $valueResolver;
        $this->dataConverter = $dataConverter;
        $this->autoIncrementFile = $autoIncrementFile;
        $this->deletedRowsFile = $deletedRowsFile;
        $this->tableSchema = $tableSchema;
    }

    private $valueResolver;

    public function getValueResolver()
    {
        return $this->valueResolver;
    }

    private $dataConverter;

    public function getDataConverter()
    {
        return $this->dataConverter;
    }

    private $columnDatas;

    private $tableSchema;

    /**
     *
     * @return TableSchema
     */
    public function getTableSchema()
    {
        return $this->tableSchema;
    }
    
    public function addColumnDefinition(ColumnDefinition $columnDefinition, ExecutionContext $executionContext)
    {
    
        /* @var $tableSchema TableSchema */
        $tableSchema = $this->getTableSchema();
        
        if (!is_null($tableSchema->getColumnIndex($columnDefinition->getName()))) {
            throw new InvalidArgumentException("Column '{$columnDefinition->getName()}' already exist!");
        }
        
        $columnPage = $this->convertColumnDefinitionToColumnSchema($columnDefinition, $executionContext);

        $columnIndex = $tableSchema->addColumnSchema($columnPage);
        
        $rowCount = $this->count();
    
        for ($rowId=0; $rowId<$rowCount; $rowId++) {
            /* @var $columnData ColumnData */
            $columnData = $this->getColumnData($rowId, $columnIndex);
            
            $columnDataRowId = $rowId % $this->getRowsPerColumnData($columnIndex);
            
            $columnData->setCellData($columnDataRowId, $defaultValueData);
        }
    }

    public function modifyColumnDefinition(
        ColumnDefinition $columnDefinition,
        ExecutionContext $executionContext
    ) {
        
        /* @var $tableSchema TableSchema */
        $tableSchema = $this->getTableSchema();
        
        $columnIndex = $tableSchema->getColumnIndex($columnDefinition->getName());
        $originalColumn = $tableSchema->getColumn($columnIndex);
        
        if (is_null($columnIndex)) {
            throw new InvalidArgumentException("Column '{$columnDefinition->getName()}' does not exist!");
        }

        $columnPage = $this->convertColumnDefinitionToColumnSchema($columnDefinition, $executionContext);
        $columnPage->setIndex($originalColumn->getIndex());
        $tableSchema->writeColumn($columnIndex, $columnPage);
    }

    protected function convertColumnDefinitionToColumnSchema(
        ColumnDefinition $columnDefinition,
        ExecutionContext $executionContext
    ) {
        
        $columnPage = new ColumnSchema();
        $columnPage->setName($columnDefinition->getName());
        
        /* @var $dataType DataType */
        $dataType = $columnDefinition->getDataType();

        $columnPage->setDataType($dataType);
    
        if (!is_null($columnDefinition->getDataTypeLength())) {
            $columnPage->setLength($columnDefinition->getDataTypeLength());
        }
        
        if (!is_null($columnDefinition->getDataTypeSecondLength())) {
            $columnPage->setSecondLength($columnDefinition->getDataTypeSecondLength());
        }
        
        $flags = 0;
    
        if ($columnDefinition->getIsAutoIncrement()) {
            $flags = $flags ^ ColumnSchema::EXTRA_AUTO_INCREMENT;
        }
    
        if (!$columnDefinition->getIsNullable()) {
            $flags = $flags ^ ColumnSchema::EXTRA_NOT_NULL;
        }
    
        if ($columnDefinition->getIsPrimaryKey()) {
            $flags = $flags ^ ColumnSchema::EXTRA_PRIMARY_KEY;
        }
            
        if ($columnDefinition->getIsUnique()) {
            $flags = $flags ^ ColumnSchema::EXTRA_UNIQUE_KEY;
        }
    
        if ($columnDefinition->getIsUnsigned()) {
            $flags = $flags ^ ColumnSchema::EXTRA_UNSIGNED;
        }
    
        if (false) {
            $flags = $flags ^ ColumnSchema::EXTRA_ZEROFILL;
        }
        
        $columnPage->setExtraFlags($flags);
    
        #$columnPage->setFKColumnIndex($index);
        #$columnPage->setFKTableIndex($index);
        
        /* @var $defaultValue Value */
        $defaultValue = $columnDefinition->getDefaultValue();
        
        if (!is_null($defaultValue)) {
            if (!$dataType->mustResolveDefaultValue()) {
                # default value must be resolved at insertion-time => save unresolved
                $defaultValueData = $this->valueResolver->resolveValue($defaultValue, $executionContext);
                $defaultValueData = $this->dataConverter->convertStringToBinary(
                    $defaultValueData,
                    $columnPage->getDataType()
                );
            } else {
                $defaultValueData = (string)$defaultValue;
            }
        } else {
            $defaultValueData = null;
        }

        $columnPage->setDefaultValue($defaultValueData);
    
        $comment = $columnDefinition->getComment();
        
        # TODO: save column comment

        return $columnPage;
    }
    
    const BYTES_PER_DATAFILE = 131072; # = 128*1024;

    protected function getRowsPerColumnData($columnId)
    {

        /* @var $columnSchemaPage ColumnSchema */
        $columnSchemaPage = $this->getTableSchema()->getColumn($columnId);

        return ceil(self::BYTES_PER_DATAFILE / $columnSchemaPage->getCellSize());
    }

    public function getColumnData($columnId)
    {
        if (is_string($columnId)) {
            $columnId = $this->getTableSchema()->getColumnIndex($columnId);
        }

        assert("is_int(\$columnId)");

        if (!isset($this->columnDatas[$columnId])) {
            throw new ErrorException("Requested column '{$columnId}' does not exist!");
        }

        return $this->columnDatas[$columnId];
    }

    ### WORK WITH DATA

    public function getCellData($rowId, $columnId)
    {

        /* @var $columnData ColumnData */
        $columnData = $this->getColumnData($columnId);

        $columnData->getCellData($rowId);
    }

    public function setCellData($rowId, $columnId, $data)
    {

        /* @var $columnData ColumnData */
        $columnData = $this->getColumnData($columnId);

        $columnData->setCellData($rowId, $data);
    }

    public function doesRowExists($rowId = null)
    {

        if (is_null($rowId)) {
            $rowId = $this->tell();
        }

        if (is_null($rowId)) {
            return false;
        }

        /* @var $tableSchema TableSchema */
        $tableSchema = $this->getTableSchema();
        
        foreach ($tableSchema->getPrimaryKeyColumns() as $columnId => $columnPage) {
            /* @var $columnPage ColumnSchema */
            
            $columnName = $columnPage->getName();
            
            /* @var $columnData ColumnData */
            $columnData = $this->getColumnData($columnId);
                
            if (!is_null($columnData->getCellData($rowId))) {
                return true;
            }
        }
        
        return false;
    }

    public function getRowCount()
    {
        $rowCount = 0;
        
        /* @var $tableSchema TableSchema */
        $tableSchema = $this->getTableSchema();

        $deletedCount = $this->getDeletedRowsCount();
        
        foreach ($tableSchema->getPrimaryKeyColumns() as $columnId => $columnPage) {
            /* @var $columnPage ColumnSchema */
            
            $columnData = $this->getColumnData($columnId);
        
            $cellCount = $columnData->count();

            $rowCount = $cellCount - $deletedCount;
        }
        
        return $rowCount;
    }
    
    public function getNamedRowData($rowId = null)
    {
        
        if (is_null($rowId)) {
            $rowId = $this->tell();
        }
        
        $rowData = $this->getRowData($rowId);
    
        $tableSchema = $this->getTableSchema();
    
        $namedRow = array();
    
        foreach ($rowData as $columnId => $value) {
            $namedRow[$tableSchema->getColumn($columnId)->getName()] = $value;
        }
    
        return $namedRow;
    }
    
    const ROWCACHE_SIZE = 256;
    
    private $rowCache = array();
    
    public function getRowData($rowId = null)
    {

        if (is_null($rowId)) {
            $rowId = $this->tell();
        }

        if (isset($this->rowCache[$rowId])) {
            return $this->rowCache[$rowId];
        }
        
        /* @var $tableSchema TableSchema */
        $tableSchema = $this->getTableSchema();

        $rowData = array();

        foreach ($tableSchema->getCachedColumnIds() as $columnId) {
            /* @var $columnPage ColumnSchema */

            /* @var $columnData ColumnData */
            $columnData = $this->getColumnData($columnId);
            
            $columnDataRowId = $rowId % $this->getRowsPerColumnData($columnId);
                
            $rowData[$columnId] = $columnData->getCellData($columnDataRowId);
        }
        
        if (count($this->rowCache) < self::ROWCACHE_SIZE) {
            $this->rowCache[$rowId] = $rowData;
        }
        
        return $rowData;
    }

    public function setRowData($rowId, array $rowData)
    {

        /* @var $tableSchema TableSchema */
        $tableSchema = $this->getTableSchema();

        foreach ($rowData as $columnId => $data) {
            /* @var $columnData ColumnData */
            $columnData = $this->getColumnData($columnId);

            $columnData->setCellData($rowId, $data);
        }

        unset($this->rowCache[$rowId]);
    }

    public function addRowData(array $rowData)
    {

        $rowId = $this->popDeletedRowStack();
        
        if (is_null($rowId)) {
            $rowId = $this->getRowCount();
        }
        
        foreach ($rowData as $columnId => $data) {
            /* @var $columnData ColumnData */
            $columnData = $this->getColumnData($columnId);

            $columnData->setCellData($rowId, $data);
        }

        return $rowId;
    }

    public function removeRow($rowId)
    {
        
        /* @var $tableSchema TableSchema */
        $tableSchema = $this->getTableSchema();
        
        foreach ($tableSchema->getCachedColumnIds() as $columnId) {
            /* @var $columnPage ColumnSchema */
            
            /* @var $columnData ColumnData */
            $columnData = $this->getColumnData($columnId);
            
            $columnDataRowId = $rowId % $this->getRowsPerColumnData($columnId);
            
            $columnData->removeCell($columnDataRowId);
        }
        
        $this->pushDeletedRowStack($rowId);

        if (isset($this->rowCache[$rowId])) {
            unset($this->rowCache[$rowId]);
        }
    }
    
    ### DELETED ROWS STACK

    const DELETEDROWS_PAGE_SIZE = 16;
    
    protected function popDeletedRowStack()
    {
        $rowId = null;

        $deletedRowsFile = $this->deletedRowsFile;
        $deletedRowsFile->lock(LOCK_EX);
        $deletedRowsFile->seek(0, SEEK_END);

        if ($deletedRowsFile->tell() !== 0) {
            $deletedRowsFile->seek(0-self::DELETEDROWS_PAGE_SIZE, SEEK_CUR);
            $sizeAfterFetch = $deletedRowsFile->tell();
            $rowId = $deletedRowsFile->read(self::DELETEDROWS_PAGE_SIZE);
            $deletedRowsFile->truncate($sizeAfterFetch);
            $rowId = $this->strdec($rowId);
        }

        $deletedRowsFile->lock(LOCK_UN);

        return $rowId;
    }
    
    protected function pushDeletedRowStack($rowId)
    {
        $deletedRowsFile = $this->deletedRowsFile;

        $rowId = $this->decstr($rowId);
        $rowId = str_pad($rowId, self::DELETEDROWS_PAGE_SIZE, "\0", STR_PAD_LEFT);
        
        $deletedRowsFile->lock(LOCK_EX);
        $deletedRowsFile->seek(0, SEEK_END);
        $deletedRowsFile->write($rowId);
        $deletedRowsFile->lock(LOCK_UN);
    }
    
    protected function getDeletedRowsCount()
    {
        $deletedRowsFile = $this->deletedRowsFile;
        $deletedRowsFile->lock(LOCK_SH);
        $deletedRowsFile->seek(0, SEEK_END);
        $count = $deletedRowsFile->tell() / self::DELETEDROWS_PAGE_SIZE;
        $deletedRowsFile->lock(LOCK_UN);

        return $count;
    }
    
    ### INDICIES

    protected $indicies = array();

    public function getIndex($indexId)
    {
        if (!is_numeric($indexId)) {
            $indexId = $this->tableSchema->getIndexIdByName($indexId);
        }

        if (!isset($this->indicies[$indexId])) {
            throw new ErrorException("Requested index {$indexId} which does not exist!");
        }
        return $this->indicies[$indexId];
    }

    ### AUTO-INCREMENT

    public function incrementAutoIncrementId()
    {
    
        $currentValue = (int)$this->getAutoIncrementId();
        $currentValue++;
    
        $file = $this->autoIncrementFile;
        $file->setData((string)$currentValue);
    }
    
    public function getAutoIncrementId()
    {
        /* @var $file FileInterface */
        $file = $this->autoIncrementFile;
    
        if ($file->getLength() <= 0) {
            $file->setData("1");
        }
    
        return $file->getData();
    }
    
    ### ITEARTOR

    private $iterator;

    private $currentRowIndex = 0;

    public function seek($rowId)
    {
        $this->setCurrentRowIndex($rowId);
    }

    public function setCurrentRowIndex($rowId)
    {
        
        if (is_null($rowId)) {
            $this->currentRowIndex = null;
            return;
        }

        if (is_string($rowId)) {
            $rowId = $this->strdec($rowId);
        }
        if (!is_int($rowId)) {
            throw new ErrorException("Row-id has to be integer!");
        }
        if (!$this->doesRowExists($rowId)) {
            throw new ErrorException("Seek to non-existing row-id '{$rowId}'!");
        }

        $this->currentRowIndex = $rowId;
        $this->isValid = true;
    }

    public function tell()
    {
        return $this->currentRowIndex;
    }

    public function count()
    {
        return $this->getRowCount();
    }

    public function usesBinaryData()
    {
        return true;
    }
    
    public function convertStringRowToDataRow(array $row)
    {

        $tableSchema = $this->getTableSchema();

        foreach ($row as $columnId => &$value) {
            if (is_null($value)) {
                continue;
            }

            /* @var $columnPage ColumnSchema */
            $columnPage = $tableSchema->getColumn($columnId);

            /* @var $dataType DataType */
            $dataType = $columnPage->getDataType();

            $value = $this->dataConverter->convertStringToBinary($value, $dataType);
        }

        return $row;
    }

    public function convertDataRowToStringRow(array $row)
    {

        $tableSchema = $this->getTableSchema();

        foreach ($row as $columnId => &$value) {
            if (is_null($value)) {
                continue;
            }

            /* @var $columnPage ColumnSchema */
            $columnPage = $tableSchema->getColumn($columnId);

            /* @var $dataType DataType */
            $dataType = $columnPage->getDataType();
                
            $value = $this->dataConverter->convertBinaryToString($value, $dataType);
        }

        return $row;
    }

    ### ITERATOR

    protected $isValid = false;

    public function rewind()
    {
        /* @var $lastColumnData ColumnData */
        $lastColumnData = null;

        $tableSchema = $this->getTableSchema();

        foreach ($tableSchema->getPrimaryKeyColumns() as $columnId => $columnPage) {
            /* @var $columnPage ColumnSchema */
            
            /* @var $columnData ColumnData */
            $columnData = $this->getColumnData($columnId);
            
            $columnData->rewind();
            $lastColumnData = $columnData;
        }

        $this->seek($lastColumnData->key());
        $this->isValid = $lastColumnData->valid();
    }

    public function valid()
    {
        return $this->isValid;
    }

    public function current()
    {
        if ($this->isValid) {
            return $this->getNamedRowData();
        }
    }

    public function key()
    {
        if ($this->isValid) {
            return $this->tell();
        }
    }

    public function next()
    {
        $tableSchema = $this->getTableSchema();

        foreach ($tableSchema->getPrimaryKeyColumns() as $columnId => $columnPage) {
            /* @var $columnPage ColumnSchema */
            
            /* @var $columnData ColumnData */
            $columnData = $this->getColumnData($columnId);
            
            $columnData->next();
            $lastColumnData = $columnData;
        }

        $this->isValid = $lastColumnData->valid();
        if ($this->isValid) {
            $this->seek($lastColumnData->key());
        }
    }
}

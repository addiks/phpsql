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
use Addiks\PHPSQL\Column\ColumnDataInterface;

class Table implements Iterator, TableInterface, UsesBinaryDataInterface
{

    use BinaryConverterTrait;

    public function __construct(
        TableSchema $tableSchema,
        array $columnDatas,
        array $indicies,
        FileInterface $autoIncrementFile,
        FileInterface $deletedRowsFile,
        DataConverter $dataConverter = null
    ) {
        if (is_null($dataConverter)) {
            $dataConverter = new DataConverter();
        }

        $this->columnDatas = $columnDatas;
        $this->indicies = $indicies;
        $this->dataConverter = $dataConverter;
        $this->autoIncrementFile = $autoIncrementFile;
        $this->deletedRowsFile = $deletedRowsFile;
        $this->tableSchema = $tableSchema;
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
    
    public function addColumn(ColumnSchema $columnSchema, ColumnDataInterface $addedColumnData)
    {
        /* @var $tableSchema TableSchema */
        $tableSchema = $this->getTableSchema();
        
        if ($tableSchema->hasColumn($columnSchema)) {
            $addedColumnId = $tableSchema->getColumnIndex($columnSchema->getName());

        } else {
            $addedColumnId = $tableSchema->addColumnSchema($columnSchema);
        }
        
        $this->columnDatas[$addedColumnId] = $addedColumnData;

        /* @var $pkColumnData ColumnData */
        $pkColumnData = null;

        foreach ($tableSchema->getPrimaryKeyColumns() as $pkColumnId => $pkColumnSchema) {
            /* @var $pkColumnSchema ColumnSchema */
            
            $pkColumnData = $this->getColumnData($pkColumnId);
            break;
        }

        if (!is_null($pkColumnData)) {
            $beforeSeek = null;
            if ($pkColumnData->valid()) {
                $beforeSeek = $pkColumnData->key();
            }

            $defaultValue = $columnSchema->getDefaultValue();

            foreach ($pkColumnData as $rowId => $pkValue) {
                $addedColumnData->setCellData($rowId, $defaultValue);
            }
    
            if (!is_null($beforeSeek)) {
                $pkColumnData->seek($beforeSeek);
            }
        }
    }

    public function modifyColumn(ColumnSchema $columnSchema, ColumnDataInterface $addedColumnData)
    {
        /* @var $tableSchema TableSchema */
        $tableSchema = $this->getTableSchema();
        
        $columnIndex = $tableSchema->getColumnIndex($columnSchema->getName());
        $originalColumn = $tableSchema->getColumn($columnIndex);
        
        if (is_null($columnIndex)) {
            throw new InvalidArgumentException("Column '{$columnSchema->getName()}' does not exist!");
        }

        $columnSchema->setIndex($originalColumn->getIndex());
        $tableSchema->writeColumn($columnIndex, $columnSchema);

        $oldColumnData = $this->getColumnData($columnIndex);

        foreach ($oldColumnData as $rowId => $cellData) {
            $addedColumnData->setCellData($rowId, $cellData);
        }

        $this->columnDatas[$columnIndex] = $addedColumnData;
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

        return $columnData->getCellData($rowId);
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

        if (is_null($rowId) || $rowId < 0) {
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
            
            $rowData[$columnId] = $columnData->getCellData($rowId);
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
            
            $columnData->removeCell($rowId);
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
        $this->setAutoIncrementId($currentValue);
    }

    public function setAutoIncrementId($newAutoIncrementId)
    {
        $file = $this->autoIncrementFile;
        $file->setData((string)$newAutoIncrementId);
    }
    
    public function getAutoIncrementId()
    {
        /* @var $file FileInterface */
        $file = $this->autoIncrementFile;
    
        if ($file->getLength() <= 0) {
            $file->setData("1");
        }
    
        return (int)$file->getData();
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

        /* @var $lastColumnSchema ColumnSchema */
        $lastColumnSchema = null;

        /* @var $tableSchema TableSchema */
        $tableSchema = $this->getTableSchema();

        foreach ($tableSchema->getPrimaryKeyColumns() as $columnId => $columnSchema) {
            /* @var $columnSchema ColumnSchema */
            
            /* @var $columnData ColumnData */
            $columnData = $this->getColumnData($columnId);
            
            $columnData->rewind();
            $lastColumnData = $columnData;
            $lastColumnSchema = $columnSchema;
        }

        if (is_null($lastColumnSchema)) {
            throw new ErrorException("No primary-columnd defined!");
        }

        if (is_null($lastColumnData)) {
            throw new ErrorException("Missing column-data for primary-key column '{$lastColumnSchema->getName()}'!");
        }

        $this->isValid = $lastColumnData->valid();
        if ($this->isValid) {
            $this->seek($lastColumnData->key());
        }
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

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
use Addiks\PHPSQL\Value\Enum\Page\Column\DataType;
use Addiks\PHPSQL\Entity\TableSchema;
use Addiks\PHPSQL\Entity\ColumnData;
use Addiks\PHPSQL\Entity\Job\Part\Value;
use Addiks\PHPSQL\DataConverter;
use Addiks\PHPSQL\ValueResolver;
use Addiks\PHPSQL\Entity\Page\ColumnPage;
use Addiks\PHPSQL\Entity\Job\Part\ColumnDefinition;
use Addiks\PHPSQL\Database;
use Addiks\PHPSQL\BinaryConverterTrait;
use Addiks\PHPSQL\CustomIterator;
use Addiks\PHPSQL\Filesystem\FilesystemInterface;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\Filesystem\FilePathes;
use Addiks\PHPSQL\TableInterface;
use Addiks\PHPSQL\UsesBinaryDataInterface;

class InternalTable implements Iterator, TableInterface, UsesBinaryDataInterface
{

    use BinaryConverterTrait;

    public function __construct(
        SchemaManager $schemaManager,
        FilesystemInterface $filesystem,
        $tableName,
        $schemaId = null,
        $valueResolver = null,
        $dataConverter = null
    ) {

        if (is_null($valueResolver)) {
            $valueResolver = new ValueResolver();
        }

        if (is_null($dataConverter)) {
            $dataConverter = new DataConverter();
        }

        if (is_null($schemaId)) {
            $schemaId = $schemaManager->getCurrentlyUsedDatabaseId();
        }

        $schema = $schemaManager->getSchema($schemaId);

        $this->schemaManager = $schemaManager;
        $this->filesystem = $filesystem;
        $this->valueResolver = $valueResolver;
        $this->dataConverter = $dataConverter;
        $this->dbSchemaId = $schemaId;
        $this->dbSchema = $schema;
        $this->tableSchema = $schemaManager->getTableSchema($tableName, $schemaId);
        $this->tableId = $schema->getTableIndex($tableName);
        $this->tableName = $tableName;
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

    private $schemaManager;

    public function getSchemaManager()
    {
        return $this->schemaManager;
    }

    private $filesystem;

    public function getFilesystem()
    {
        return $this->filesystem;
    }

    private $dbSchemaId;

    public function getDBSchemaId()
    {
        return $this->dbSchemaId;
    }

    private $dbSchema;

    public function getDBSchema()
    {
        return $this->dbSchema;
    }

    private $tableName;

    public function getTableName()
    {
        return $this->tableName;
    }

    private $tableId;

    public function getTableId()
    {
        return $this->tableId;
    }

    private $tableSchema;

    /**
     *
     * @return TableSchema
     */
    public function getTableSchema()
    {
        return $this->tableSchema;
    }
    
    public function addColumnDefinition(ColumnDefinition $columnDefinition)
    {
    
        /* @var $tableSchema TableSchema */
        $tableSchema = $this->getTableSchema();
        
        if (!is_null($tableSchema->getColumnIndex($columnDefinition->getName()))) {
            throw new Conflict("Column '{$columnDefinition->getName()}' already exist!");
        }
        
        $columnPage = new ColumnPage();
        $columnPage->setName($columnDefinition->getName());
        $columnPage->setDataType($columnDefinition->getDataType());
    
        if (!is_null($columnDefinition->getDataTypeLength())) {
            $columnPage->setLength($columnDefinition->getDataTypeLength());
        }
        
        if (!is_null($columnDefinition->getDataTypeSecondLength())) {
            $columnPage->setSecondLength($columnDefinition->getDataTypeSecondLength());
        }
        
        $flags = 0;
    
        if ($columnDefinition->getIsAutoIncrement()) {
            $flags = $flags ^ ColumnPage::EXTRA_AUTO_INCREMENT;
        }
    
        if (!$columnDefinition->getIsNullable()) {
            $flags = $flags ^ ColumnPage::EXTRA_NOT_NULL;
        }
    
        if ($columnDefinition->getIsPrimaryKey()) {
            $flags = $flags ^ ColumnPage::EXTRA_PRIMARY_KEY;
        }
            
        if ($columnDefinition->getIsUnique()) {
            $flags = $flags ^ ColumnPage::EXTRA_UNIQUE_KEY;
        }
    
        if ($columnDefinition->getIsUnsigned()) {
            $flags = $flags ^ ColumnPage::EXTRA_UNSIGNED;
        }
    
        if (false) {
            $flags = $flags ^ ColumnPage::EXTRA_ZEROFILL;
        }
        
        $columnPage->setExtraFlags($flags);
    
        #$columnPage->setFKColumnIndex($index);
        #$columnPage->setFKTableIndex($index);
        
        /* @var $defaultValue Value */
        $defaultValue = $columnDefinition->getDefaultValue();
        
        if (!is_null($defaultValue)) {
            $defaultValueData = $this->valueResolver->resolveValue($defaultValue);
            $defaultValueData = $this->dataConverter->convertStringToBinary(
                $defaultValueData,
                $columnPage->getDataType()
            );
        } else {
            $defaultValueData = null;
        }
        
        $columnIndex = $this->getTableSchema()->addColumnPage($columnPage);
        
        $rowCount = $this->count();
    
        for ($rowId=0; $rowId<$rowCount; $rowId++) {
            /* @var $columnData ColumnData */
            $columnData = $this->getColumnDataByRowIndex($rowId, $columnIndex);
            
            $columnDataRowId = $rowId % $this->getRowsPerColumnData($columnIndex);
            
            $columnData->setCellData($columnDataRowId, $defaultValueData);
        }
    
        $comment = $columnDefinition->getComment();
    }
    
    const BYTES_PER_DATAFILE = 131072; # = 128*1024;

    protected function getRowsPerColumnData($columnId)
    {

        /* @var $columnSchemaPage ColumnPage */
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
    public function getColumnDataByRowIndex($rowIndex, $columnId, &$columnDataIndex = 0)
    {

        if (is_string($columnId)) {
            $columnId = $this->getTableSchema()->getColumnIndex($columnId);
        }

        assert("is_int(\$columnId)");

        if (!isset($this->columnDataCache[$columnId])) {
            $this->columnDataCache[$columnId] = array();
        }

        $rowsPerColumnData = $this->getRowsPerColumnData($columnId);

        $columnDataIndex = floor($rowIndex / $rowsPerColumnData);

        if (!isset($this->columnDataCache[$columnId][$columnDataIndex])) {
            $columnDataFilePath = sprintf(
                FilePathes::FILEPATH_COLUMN_DATA_FILE,
                $this->dbSchemaId,
                $this->getTableName(),
                $columnId,
                $columnDataIndex
            );

            $columnDataFile = $this->filesystem->getFile($columnDataFilePath);

            /* @var $columnSchemaPage ColumnPage */
            $columnSchemaPage = $this->getTableSchema()->getColumn($columnId);

            /* @var $columnData ColumnData */
            $columnData = new ColumnData($columnDataFile, $columnSchemaPage);

            if ($columnDataFile->getLength() <= 0) {
                $columnData->preserveSpace($this->getRowsPerColumnData($columnId));
            }

            $this->columnDataCache[$columnId][$columnDataIndex] = $columnData;
        }

        return $this->columnDataCache[$columnId][$columnDataIndex];
    }

    ### WORK WITH DATA

    public function getCellData($rowId, $columnId)
    {

        /* @var $columnData ColumnData */
        $columnData = $this->getColumnDataByRowIndex($rowId, $columnId);

        $columnData->getCellData($rowId);
    }

    public function setCellData($rowId, $columnId, $data)
    {

        /* @var $columnData ColumnData */
        $columnData = $this->getColumnDataByRowIndex($rowId, $columnId);

        $columnData->setCellData($rowId, $data);
    }

    public function getRowExists($rowId = null)
    {

        if (is_null($rowId)) {
            $rowId = $this->getCurrentRowIndex();
        }

        if (is_null($rowId)) {
            return false;
        }

        /* @var $tableSchema TableSchema */
        $tableSchema = $this->getTableSchema();
        
        foreach ($tableSchema->getPrimaryKeyColumns() as $columnId => $columnPage) {
            /* @var $columnPage ColumnPage */
            
            $columnName = $columnPage->getName();
            
            /* @var $columnData ColumnData */
            $columnData = $this->getColumnDataByRowIndex($rowId, $columnId, $columnDataIndex);
                
            $columnDataRowId = $rowId % $this->getRowsPerColumnData($columnId);
            
            if ($columnData->count() < $columnDataRowId) {
                return false;
            }
            
            if (!is_null($columnData->getCellData($columnDataRowId))) {
                return true;
            }
        }
        
        return false;
    }

    public function getRowCount()
    {
        
        /* @var $tableSchema TableSchema */
        $tableSchema = $this->getTableSchema();
        
        foreach ($tableSchema->getPrimaryKeyColumns() as $columnId => $columnPage) {
            /* @var $columnPage ColumnPage */
            
            $lastDataIndex = $this->getTableColumnDataLastDataIndex(
                $columnId,
                $this->getTableName(),
                $this->getDBSchemaId()
            );
            
            if (is_null($lastDataIndex)) {
                return 0;
            }
            
            $columnDataFilePath = sprintf(
                FilePathes::FILEPATH_COLUMN_DATA_FILE,
                $this->dbSchemaId,
                $this->getTableName(),
                $columnId,
                $lastDataIndex
            );

            $columnDataFile = $this->filesystem->getFile($columnDataFilePath);

            $columnSchemaPage = $tableSchema->getColumn(0);
            
            /* @var $columnData ColumnData */
            $columnData = new ColumnData($columnDataFile, $columnSchemaPage);
            
            $lastColumnDataIndex = $columnData->count();
            
            $beforeLastColumnDataRowCount = $this->getRowsPerColumnData(0) * $lastDataIndex;
            
            $lastIndex = $lastColumnDataIndex + $beforeLastColumnDataRowCount;
            
            return $lastIndex +1 -$this->getDeletedRowsCount();
            
        }
        
        return 0;
    }
    
    protected function getTableColumnDataLastDataIndex($columnId, $tableName, $schemaId = null)
    {
        assert("is_int(\$columnId);");

        if (is_null($schemaId)) {
            $schemaId = $this->schemaManager->getCurrentlyUsedDatabaseId();
        }

        $folderPath = sprintf(
            FilePathes::FILEPATH_COLUMN_DATA_FOLDER,
            $schemaId,
            (string)$tableName,
            (string)$columnId
        );
        
        $lastDataIndex = null;
        foreach ($this->filesystem->getDirectoryIterator($folderPath) as $item) {
            /* @var $item DirectoryIterator */

            $fileName = $item->getFilename();
            $dataIndex = substr($fileName, 0, strrpos($fileName, "."));

            if ($lastDataIndex < $dataIndex) {
                $lastDataIndex = $dataIndex;
            }
        }
        
        return $lastDataIndex;
    }
    
    public function getNamedRowData($rowId = null)
    {
        
        if (is_null($rowId)) {
            $rowId = $this->getCurrentRowIndex();
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
            $rowId = $this->getCurrentRowIndex();
        }

        if (isset($this->rowCache[$rowId])) {
            return $this->rowCache[$rowId];
        }
        
        /* @var $tableSchema TableSchema */
        $tableSchema = $this->getTableSchema();

        $rowData = array();

        foreach ($tableSchema->getCachedColumnIds() as $columnId) {
            /* @var $columnPage ColumnPage */

            /* @var $columnData ColumnData */
            $columnData = $this->getColumnDataByRowIndex($rowId, $columnId);
            
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
            $columnData = $this->getColumnDataByRowIndex($rowId, $columnId);

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
            $columnData = $this->getColumnDataByRowIndex($rowId, $columnId);

            $columnData->setCellData($rowId, $data);
        }

        return $rowId;
    }

    public function removeRow($rowId)
    {
        
        /* @var $tableSchema TableSchema */
        $tableSchema = $this->getTableSchema();
        
        foreach ($tableSchema->getCachedColumnIds() as $columnId) {
            /* @var $columnPage ColumnPage */
            
            /* @var $columnData ColumnData */
            $columnData = $this->getColumnDataByRowIndex($rowId, $columnId);
            
            $columnDataRowId = $rowId % $this->getRowsPerColumnData($columnId);
            
            $columnData->removeCell($columnDataRowId);
        }
        
        $this->pushDeletedRowStack($rowId);
    }
    
    const DELETEDROWS_PAGE_SIZE = 16;
    
    protected function popDeletedRowStack()
    {
        $rowId = null;

        $deletedRowsFilepath = sprintf(
            FilePathes::FILEPATH_DELETED_ROWS,
            $this->getDBSchemaId(),
            $this->getTableName()
        );

        $deletedRowsFile = $this->filesystem->getFile($deletedRowsFilepath);
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
        $deletedRowsFilepath = sprintf(
            FilePathes::FILEPATH_DELETED_ROWS,
            $this->getDBSchemaId(),
            $this->getTableName()
        );

        $deletedRowsFile = $this->filesystem->getFile($deletedRowsFilepath);

        $rowId = $this->decstr($rowId);
        $rowId = str_pad($rowId, self::DELETEDROWS_PAGE_SIZE, "\0", STR_PAD_LEFT);
        
        $deletedRowsFile->lock(LOCK_EX);
        $deletedRowsFile->seek(0, SEEK_END);
        $deletedRowsFile->write($rowId);
        $deletedRowsFile->lock(LOCK_UN);
    }
    
    protected function getDeletedRowsCount()
    {
        $deletedRowsFilepath = sprintf(
            FilePathes::FILEPATH_DELETED_ROWS,
            $this->getDBSchemaId(),
            $this->getTableName()
        );

        $deletedRowsFile = $this->filesystem->getFile($deletedRowsFilepath);
        $deletedRowsFile->lock(LOCK_SH);
        $deletedRowsFile->seek(0, SEEK_END);
        $count = $deletedRowsFile->tell() / self::DELETEDROWS_PAGE_SIZE;
        $deletedRowsFile->lock(LOCK_UN);

        return $count;
    }
    
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
        if (!$this->getRowExists($rowId)) {
            throw new ErrorException("Seek to non-existing row-id '{$rowId}'!");
        }

        $this->currentRowIndex = $rowId;
    }

    public function getCurrentRowIndex()
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

            /* @var $columnPage ColumnPage */
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

            /* @var $columnPage ColumnPage */
            $columnPage = $tableSchema->getColumn($columnId);

            /* @var $dataType DataType */
            $dataType = $columnPage->getDataType();
                
            $value = $this->dataConverter->convertBinaryToString($value, $dataType);
        }

        return $row;
    }

    ### ITERATOR

    public function rewind()
    {
        if ($this->count()>0) {
            $this->seek(0);
        }
    }

    public function valid()
    {
        return $this->getRowExists();
    }

    public function current()
    {
        if (!$this->getRowExists()) {
            return null;
        }
        return $this->getNamedRowData();
    }

    public function key()
    {
        return $this->getCurrentRowIndex();
    }

    public function next()
    {
        $newRowId = $this->getCurrentRowIndex()+1;
        if ($this->getRowExists($newRowId)) {
            $this->seek($newRowId);
        } else {
            $this->seek(null);
        }
    }
}

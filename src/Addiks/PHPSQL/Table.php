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

namespace Addiks\PHPSQL;

use Addiks\PHPSQL\Entity\Job\Part\ColumnDefinition;
use Addiks\PHPSQL\Table\InternalTable;
use Addiks\PHPSQL\CustomIterator;
use IteratorAggregate;
use Countable;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\Filesystem\FilesystemInterface;
use Addiks\PHPSQL\Filesystem\FilePathes;

/**
 * This represents a table.
 */
class Table implements IteratorAggregate, Countable, TableInterface
{

    public function __construct(
        SchemaManager $schemaManager,
        FilesystemInterface $filesystem,
        $tableName,
        $schemaId = null
    ) {
        
        switch($schemaId){
            
            case SchemaManager::DATABASE_ID_META_INDICES:
                $tableBackend = new InternalIndices($tableName, $schemaId);
                break;
                
            case SchemaManager::DATABASE_ID_META_MYSQL:
                $tableBackend = new MySQLTable($tableName, $schemaId);
                break;
                
            case SchemaManager::DATABASE_ID_META_INFORMATION_SCHEMA:
                $tableBackend = new InformationSchema($tableName, $schemaId);
                break;
            
            default:
                $tableBackend = new InternalTable(
                    $schemaManager,
                    $filesystem,
                    $tableName,
                    $schemaId
                );
                break;
        }
        
        $this->filesystem = $filesystem;
        $this->tableBackend = $tableBackend;
        $this->schemaManager = $schemaManager;
        $this->schemaId = $schemaId;
        $this->tableName = $tableName;
    }

    protected $schemaManager;

    protected $tableName;

    protected $schemaId;

    protected $filesystem;

    public function getFilesystem()
    {
        return $this->filesystem;
    }
    
    public function getIsSuccess()
    {
        return true;
    }
    
    private $lastInsertId = array();
    
    /**
     * @return array
     */
    public function getLastInsertId()
    {
        return $this->lastInsertId;
    }
    
    public function setLastInsertId(array $row)
    {
        $this->lastInsertId;
    }
    
    public function getHeaders()
    {
        
        $headers = array();
        foreach ($this->getTableSchema()->getColumnIterator() as $columnPage) {
            /* @var $columnPage Column */
            
            $headers[] = $columnPage->getName();
        }
        return $headers;
    }
    
    public function getHasResultRows()
    {
        return $this->count() > 0;
    }
    
    /**
     *
     * @var TableInterface
     */
    private $tableBackend;
    
    /**
     *
     * @return TableInterface
     */
    public function getBackend()
    {
        return $this->tableBackend;
    }
    
    public function getDBSchemaId()
    {
        return $this->tableBackend->getDBSchemaId();
    }
    
    public function getDBSchema()
    {
        return $this->tableBackend->getDBSchema();
    }
    
    public function getTableName()
    {
        return $this->tableBackend->getTableName();
    }
    
    public function getTableId()
    {
        return $this->tableBackend->getTableId();
    }
    
    /**
     *
     * @return TableSchema
     */
    public function getTableSchema()
    {
        return $this->tableBackend->getTableSchema();
    }
    
    public function addColumnDefinition(ColumnDefinition $columnDefinition)
    {
        return $this->tableBackend->addColumnDefinition($columnDefinition);
    }
    
    public function getIterator()
    {
        return $this->tableBackend->getIterator();
    }
    
    public function getCellData($rowId, $columnId)
    {
        return $this->tableBackend->getCellData($rowId, $columnId);
    }
    
    public function setCellData($rowId, $columnId, $data)
    {
        return $this->tableBackend->setCellData($rowId, $columnId, $data);
    }
    
    public function getRowData($rowId = null)
    {
        return $this->tableBackend->getRowData($rowId);
    }
    
    public function setRowData($rowId, array $rowData)
    {
        return $this->tableBackend->setRowData($rowId, $rowData);
    }
    
    public function addRowData(array $rowData)
    {
        return $this->tableBackend->addRowData($rowData);
    }

    public function removeRow($rowId)
    {
        return $this->tableBackend->removeRow($rowId);
    }
    
    public function getRowExists($rowId = null)
    {
        return $this->tableBackend->getRowExists($rowId);
    }
    
    public function count()
    {
        return $this->tableBackend->count();
    }
    
    public function seek($rowId)
    {
        return $this->tableBackend->seek($rowId);
    }
    
    public function convertStringRowToDataRow(array $row)
    {
        return $this->tableBackend->convertStringRowToDataRow($row);
    }
    
    public function convertDataRowToStringRow(array $row)
    {
        return $this->tableBackend->convertDataRowToStringRow($row);
    }
    
    ### INDICIES

    protected $indicies = array();

    public function getIndex($indexName)
    {
        if (!isset($this->indicies[$indexName])) {
            $this->indicies[$indexName] = new Index(
                $this->filesystem,
                $this->schemaManager,
                $indexName,
                $this->tableName,
                $this->schemaId
            );
        }
        return $this->indicies[$indexName];
    }

    ### HELPER
    
    /**
     * @return File
     */
    protected function getAutoIncrementFile()
    {
    
        $filePath = sprintf(
            FilePathes::FILEPATH_AUTOINCREMENT,
            $this->getDBSchemaId(),
            $this->getTableName()
        );

        /* @var $file FileResourceProxy */
        $file = $this->filesystem->getFile($filePath);
    
        return $file;
    }
    
    public function incrementAutoIncrementId()
    {
    
        $currentValue = (int)$this->getAutoIncrementId();
        $currentValue++;
    
        $file = $this->getAutoIncrementFile();
        $file->setData((string)$currentValue);
    }
    
    public function getAutoIncrementId()
    {
        /* @var $file FileResourceProxy */
        $file = $this->getAutoIncrementFile();
    
        if ($file->getLength() <= 0) {
            $file->setData("1");
        }
    
        return $file->getData();
    }
    
    /**
     * Alias of fetchArray
     * @return array
     */
    public function fetch()
    {
        return $this->fetchArray();
    }
    
    public function fetchArray()
    {
    
        $row = $this->fetchAssoc();
    
        $number = 0;
        foreach ($row as $value) {
            $row[$number] = $value;
            $number++;
        }
    
        return $row;
    }
    
    public function fetchAssoc()
    {
    
        $row = $this->current();
        $this->next();
    
        return $row;
    }
    
    public function fetchRow()
    {
    
        $row = $this->fetchAssoc();
    
        $returnRow = array();
    
        foreach ($row as $value) {
            $returnRow[] = $value;
        }
    
        return $returnRow;
    }
    
    private $columnMetaData = array();
    
    public function setColumnMetaData($columnName, array $data)
    {
        $this->columnMetaData[$columnName] = $data;
    }
    
    public function getColumnMetaData($columnName)
    {
        return $this->columnMetaData[$columnName];
    }
}

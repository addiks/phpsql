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

use Addiks\PHPSQL\Entity\Storage;
use Addiks\PHPSQL\Entity\Job\Part\ColumnDefinition;
use Addiks\PHPSQL\Table\InternalTable;
use Addiks\PHPSQL\CustomIterator;
use IteratorAggregate;
use Countable;

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
        
        switch(true){
            
            case $schemaId === Database::DATABASE_ID_META_INDICES:
                /* @var $tableBackend InternalIndices */
                $tableBackend = new InternalIndices($tableName, $schemaId);
                break;
                
            case $schemaId === Database::DATABASE_ID_META_MYSQL:
                /* @var $tableBackend MySQLTable */
                $tableBackend = new MySQLTable($tableName, $schemaId);
                break;
                
            case $schemaId === Database::DATABASE_ID_META_INFORMATION_SCHEMA:
                /* @var $tableBackend InformationSchema */
                $tableBackend = new InformationSchema($tableName, $schemaId);
                break;
            
            default:
                /* @var $tableBackend Internal */
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
    }

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
    
    ### HELPER
    
    /**
     * @return Storage
     */
    protected function getAutoIncrementStorage()
    {
    
        /* @var $storage Storage */
        $storage = $this->getTableAutoIncrementIdStorage($this->getTableName(), $this->getDBSchemaId());
    
        return $storage;
    }
    
    public function incrementAutoIncrementId()
    {
    
        $currentValue = (int)$this->getAutoIncrementId();
        $currentValue++;
    
        $storage = $this->getAutoIncrementStorage();
        $storage->setData((string)$currentValue);
    }
    
    public function getAutoIncrementId()
    {
    
        /* @var $storage Storage */
        $storage = $this->getAutoIncrementStorage();
    
        if ($storage->getLength() <= 0) {
            $storage->setData("1");
        }
    
        return $storage->getData();
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

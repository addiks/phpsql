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

namespace Addiks\PHPSQL\Resource;

use Addiks\PHPSQL\Entity\Storage;

use Addiks\PHPSQL\Entity\Job\Part\ColumnDefinition;

use Addiks\PHPSQL\Resource\Table\Internal;

use Addiks\Common\Resource;

use Addiks\Protocol\Entity\Exception\Error;

use Addiks\Common\Tool\CustomIterator;

/**
 * This represents a table.
 *
 * @Addiks\Singleton(negated=true)
 */
class Table extends Resource implements \IteratorAggregate, \Countable, TableInterface
{
    
    use StoragesProxyTrait;
    
    public function __construct($tableName, $schemaId = null)
    {
        
        switch(true){
            
            case $schemaId === Database::DATABASE_ID_META_INDICES:
                /* @var $tableBackend InternalIndices */
                $this->factorize($tableBackend, [$tableName, $schemaId]);
                break;
                
            case $schemaId === Database::DATABASE_ID_META_MYSQL:
                /* @var $tableBackend MySQL */
                $this->factorize($tableBackend, [$tableName, $schemaId]);
                break;
                
            case $schemaId === Database::DATABASE_ID_META_INFORMATION_SCHEMA:
                /* @var $tableBackend InformationSchema */
                $this->factorize($tableBackend, [$tableName, $schemaId]);
                break;
            
            default:
                /* @var $tableBackend Internal */
                $this->factorize($tableBackend, [$tableName, $schemaId]);
                break;
        }
        
        $this->tableBackend = $tableBackend;
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
        
        /* @var $backend TableInterface */
        $backend = $this->tableBackend;
        
        return $backend->getDBSchemaId();
    }
    
    public function getDBSchema()
    {
        
        /* @var $backend TableInterface */
        $backend = $this->tableBackend;
        
        return $backend->getDBSchema();
    }
    
    public function getTableName()
    {
        
        /* @var $backend TableInterface */
        $backend = $this->tableBackend;
        
        return $backend->getTableName();
    }
    
    public function getTableId()
    {
        
        /* @var $backend TableInterface */
        $backend = $this->tableBackend;
        
        return $backend->getTableId();
    }
    
    /**
     *
     * @return TableSchema
     */
    public function getTableSchema()
    {
        
        /* @var $backend TableInterface */
        $backend = $this->tableBackend;
        
        return $backend->getTableSchema();
    }
    
    public function addColumnDefinition(ColumnDefinition $columnDefinition)
    {
        
        /* @var $backend TableInterface */
        $backend = $this->tableBackend;
        
        return $backend->addColumnDefinition($columnDefinition);
    }
    
    public function getIterator()
    {
        
        /* @var $backend TableInterface */
        $backend = $this->tableBackend;
        
        return $backend->getIterator();
    }
    
    public function getCellData($rowId, $columnId)
    {
        
        /* @var $backend TableInterface */
        $backend = $this->tableBackend;
        
        return $backend->getCellData($rowId, $columnId);
    }
    
    public function setCellData($rowId, $columnId, $data)
    {
        
        /* @var $backend TableInterface */
        $backend = $this->tableBackend;
        
        return $backend->setCellData($rowId, $columnId, $data);
    }
    
    public function getRowData($rowId = null)
    {
        
        /* @var $backend TableInterface */
        $backend = $this->tableBackend;
        
        return $backend->getRowData($rowId);
    }
    
    public function setRowData($rowId, array $rowData)
    {
        
        /* @var $backend TableInterface */
        $backend = $this->tableBackend;
        
        return $backend->setRowData($rowId, $rowData);
    }
    
    public function addRowData(array $rowData)
    {
        
        /* @var $backend TableInterface */
        $backend = $this->tableBackend;
        
        return $backend->addRowData($rowData);
    }

    public function removeRow($rowId)
    {
        
        /* @var $backend TableInterface */
        $backend = $this->tableBackend;
        
        return $backend->removeRow($rowId);
    }
    
    public function getRowExists($rowId = null)
    {
        
        /* @var $backend TableInterface */
        $backend = $this->tableBackend;
        
        return $backend->getRowExists($rowId);
    }
    
    public function count()
    {
        
        /* @var $backend TableInterface */
        $backend = $this->tableBackend;
        
        return $backend->count();
    }
    
    public function seek($rowId)
    {
        
        /* @var $backend TableInterface */
        $backend = $this->tableBackend;
        
        return $backend->seek($rowId);
    }
    
    public function convertStringRowToDataRow(array $row)
    {
        
        /* @var $backend TableInterface */
        $backend = $this->tableBackend;
        
        return $backend->convertStringRowToDataRow($row);
    }
    
    public function convertDataRowToStringRow(array $row)
    {
        
        /* @var $backend TableInterface */
        $backend = $this->tableBackend;
        
        return $backend->convertDataRowToStringRow($row);
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

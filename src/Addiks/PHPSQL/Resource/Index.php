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

use Addiks\PHPSQL\Entity\Page\Column;
use Addiks\PHPSQL\Entity\TableSchema;
use Addiks\PHPSQL\Entity\Index\HashTable;
use Addiks\PHPSQL\Entity\Index\BTree;
use Addiks\PHPSQL\Value\Enum\Page\Index\Engine;
use Addiks\PHPSQL\Entity\Page\Schema\Index as IndexPage;
use Addiks\PHPSQL\Service\BinaryConverterTrait;
use Addiks\PHPSQL\Entity\Storage;
use Addiks\PHPSQL\Resource\Database;
use Addiks\PHPSQL\Tool\CustomIterator;
use ErrorException;

/**
 *
 * @author gerrit
 * @Addiks\Singleton(negated=true)
 */
class Index implements \IteratorAggregate
{
    
    use StoragesProxyTrait;
    use BinaryConverterTrait;
    
    public function __construct($indexId, $tableName, $schemaId = null)
    {
        
        $this->schemaId  = $schemaId;
        $this->tableName = (string)$tableName;
        $this->indexId   = (int)$indexId;
    }
    
    private $schemaId = null;
    
    public function getSchemaId()
    {
        if (is_null($this->schemaId)) {
            /* @var $databaseResource Database */
            $this->factorize($databaseResource);
            
            $this->schemaId = $databaseResource->getCurrentlyUsedDatabaseId();
        }
        return $this->schemaId;
    }
    
    private $tableName;
    
    public function getTableName()
    {
        return $this->tableName;
    }
    
    public function getTableIndex()
    {
        
        /* @var $databaseResource Database */
        $this->factorize($databaseResource);
        
        return $databaseResource->getSchema($this->schemaId)->getTableIndex($this->tableName);
    }
    
    private $indexId;
    
    public function getIndexId()
    {
        return $this->indexId;
    }
    
    public function getTableSchema()
    {
        
        /* @var $databaseResource Database */
        $this->factorize($databaseResource);
        
        return $databaseResource->getTableSchema($this->tableName, $this->schemaId);
    }
    
    /**
     * @return Index
     */
    public function getIndexPage()
    {
        return $this->getTableSchema()->getIndexPage($this->indexId);
    }
    
    protected $indexBackend;
    
    private $keyLengthStorage;
    
    private function getKeyLengthStorage()
    {
        
        if (is_null($this->keyLengthStorage)) {
            /* @var $indexPage IndexPage */
            $indexPage = $this->getIndexPage();
                
            $this->keyLengthStorage = $this->getStorage("Databases/{$this->getSchemaId()}/Tables/{$this->getTableIndex()}/Indices/{$indexPage->getName()}.length");

        }
        return $this->keyLengthStorage;
    }
    
    /**
     *
     *
     * @return Interface
     */
    public function getIndexBackend()
    {
        
        if (is_null($this->indexBackend)) {
            /* @var $indexPage IndexPage */
            $indexPage = $this->getIndexPage();
            
            /* @var $storage Storage */
            $storage = $this->getTableColumnIndexStorage($indexPage->getName(), $this->getTableName(), $this->getSchemaId());
            
            if (!$indexPage->isUnique()) {
                /* @var $storage Storage */
                $doublesStorage = $this->getTableColumnIndexDoublesStorage($indexPage->getName(), $this->getTableName(), $this->getSchemaId());
            }
            
            switch($indexPage->getEngine()){
                
                default:
                case Engine::RTREE():
                    trigger_error("Requested unimplemented INDEX-ENGINE {$indexPage->getEngine()->getName()}, using B-TREE instead!", E_USER_NOTICE);
                
                case Engine::BTREE():
                    
                    /* @var $btree BTree */
                    $this->factorize($btree, [$storage, $this->getIndexPage()->getKeyLength()]);
                    
                    if (!$indexPage->isUnique()) {
                        $btree->setDoublesStorage($doublesStorage);
                    }
                    
                    $this->indexBackend = $btree;
                    break;
                    
                    
                case Engine::HASH():
                    
                    /* @var $hashtable HashTable */
                    $this->factorize($hashtable, [$storage, $this->getIndexPage()->getKeyLength()]);
                    $hashtable->setCacheBackend($this->getUsableCacheBackend());
                        
                    if (!$indexPage->isUnique()) {
                        $hashtable->setDoublesStorage($doublesStorage);
                    }
                        
                    $this->indexBackend = $hashtable;
                    break;
                    
            }
        }
        
        return $this->indexBackend;
    }
    
    /**
     * Updates the data in the index if the related data from the row has changed.
     *
     * @param int $rowId
     * @param array $oldRow
     * @param array $newRow
     */
    public function update($rowId, $oldRow, $newRow)
    {
        
        /* @var $tableSchema TableSchema */
        $tableSchema = $this->getTableSchema();
        
        /* @var $indexPage IndexPage */
        $indexPage = $this->getIndexPage();
        
        $useValueOld = "";
        $useValueNew = "";
        
        foreach ($indexPage->getColumns() as $columnId) {
            /* @var $columnPage Column */
            $columnPage = $tableSchema->getColumn($columnId);
            
            $keyLength = $columnPage->getCellSize();
            
            $valueCellOld = $oldRow[$columnId];
            $valueCellNew = $newRow[$columnId];
            
            $useValueOld .= str_pad($valueCellOld, $keyLength, "\0", STR_PAD_LEFT);
            $useValueNew .= str_pad($valueCellNew, $keyLength, "\0", STR_PAD_LEFT);
        }
        
        $this->stringIncrement($useValueOld);
        $this->stringIncrement($useValueNew);
        
        if ($useValueNew !== $useValueOld) {
            $rowId = str_pad($rowId, $completeKeyLength, "\0", STR_PAD_LEFT);
            $this->stringIncrement($rowId);
        
            $this->getIndexBackend()->remove($useValueOld, $rowId);
            $this->getIndexBackend()->insert($useValueNew, $rowId);
        }
    }
    
    public function search(array $row)
    {
        
        /* @var $tableSchema TableSchema */
        $tableSchema = $this->getTableSchema();
        
        /* @var $indexPage IndexPage */
        $indexPage = $this->getIndexPage();
        
        // merge values of combined keys
        $useValue = "";
        
        foreach ($indexPage->getColumns() as $columnId) {
            /* @var $columnPage Column */
            $columnPage = $tableSchema->getColumn($columnId);

            if (isset($row[$columnId])) {
                $valueCell = $row[$columnId];
            } else {
                $valueCell = null;
            }
            
            $keyLength = $columnPage->getCellSize();
            $useValue .= str_pad($valueCell, $keyLength, "\0", STR_PAD_LEFT);
        }
        
        $this->stringIncrement($useValue);
        
        $result = $this->getIndexBackend()->search($useValue);
        
        if (is_string($result)) {
            $this->stringDecrement($result);
            
        } else {
            foreach ($result as &$rowId) {
                $this->stringDecrement($rowId);
            }
        }
        
        return $result;
    }
    
    public function insert(array $row, $rowId)
    {
        
        /* @var $tableSchema TableSchema */
        $tableSchema = $this->getTableSchema();
        
        /* @var $indexPage IndexPage */
        $indexPage = $this->getIndexPage();
        
        // merge values of combined keys
        $useValue = "";
        
        $completeKeyLength = 0;
        foreach ($indexPage->getColumns() as $columnId) {
            /* @var $columnPage Column */
            $columnPage = $tableSchema->getColumn($columnId);
            
            if (!isset($row[$columnId]) || is_null($row[$columnId])) {
                continue; // do not index NULL values
            }
            
            $valueCell = $row[$columnId];
            
            if (!is_string($valueCell)) {
                $typeString = gettype($valueCell);
                $valueCell = var_export($valueCell, true);
                throw new ErrorException("Cannot index non-string: ({$typeString}){$valueCell}");
            }
                    
            $keyLength = $columnPage->getCellSize();
            $completeKeyLength += $keyLength;
            $useValue .= str_pad($valueCell, $keyLength, "\0", STR_PAD_LEFT);
        }
        
        $rowId = str_pad($rowId, $completeKeyLength, "\0", STR_PAD_LEFT);
        
        $this->stringIncrement($useValue);
        $this->stringIncrement($rowId);
        
        return $this->getIndexBackend()->insert($useValue, $rowId);
    }
    
    public function remove(array $row, $rowId)
    {
        
        /* @var $tableSchema TableSchema */
        $tableSchema = $this->getTableSchema();
        
        /* @var $indexPage IndexPage */
        $indexPage = $this->getIndexPage();
        
        // merge values of combined keys
        $useValue = "";
        
        $completeKeyLength = 0;
        foreach ($indexPage->getColumns() as $columnId) {
            /* @var $columnPage Column */
            $columnPage = $tableSchema->getColumn($columnId);
                
            $valueCell = $row[$columnId];
            $keyLength = $columnPage->getCellSize();
            $completeKeyLength += $keyLength;
            $useValue .= str_pad($valueCell, $keyLength, "\0", STR_PAD_LEFT);
        }
        
        $rowId = str_pad($rowId, $completeKeyLength, "\0", STR_PAD_LEFT);
        
        $this->stringIncrement($useValue);
        $this->stringIncrement($rowId);
        
        $this->getIndexBackend()->remove($useValue, $rowId);
    }
    
    private $doublesStorage;
    
    public function getDoublesStorage()
    {
        return $this->doublesStorage;
    }
    
    public function setDoublesStorage(Storage $storage)
    {
        $this->doublesStorage = $storage;
        if ($storage->getLength()===0) {
            $storage->setData(str_pad("", $this->keyLength*2, "\0"));
        }
    }
    
    public function getIterator(array $beginValue = null, array $endValue = null)
    {
        
        $tableSchema = $this->getTableSchema();
        $indexPage = $this->getIndexPage();
        $indexResource = $this;
        
        if (is_string($beginValue)) {
            $this->stringIncrement($beginValue);
        }
        if (is_string($endValue)) {
            $this->stringIncrement($endValue);
        }
        
        $indexIterator = $this->getIndexBackend()->getIterator($beginValue, $endValue);
        
        return new CustomIterator($indexIterator, [
                
            'current' => function ($rawValue) use ($tableSchema, $indexPage, $indexResource) {
                $indexResource->stringDecrement($rawValue);
                
                $dataRow = array();
                $position = 0;
                foreach ($indexPage->getColumns() as $columnId) {
                    $keyLength = $tableSchema->getColumn($columnId)->getLength();
                    
                    $dataRow[$columnId] = ltrim(substr($rawValue, $position, $keyLength), "\0");
                    
                    $dataRow[$columnId] = $this->strdec($dataRow[$columnId]);
                    
                    $position += $keyLength;
                }
                
                return $dataRow;
            }
            
        ]);
    }
}

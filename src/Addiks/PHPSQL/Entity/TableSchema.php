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

namespace Addiks\PHPSQL\Entity;

use Addiks\PHPSQL\Entity\Page\Schema\Index;
use Addiks\PHPSQL\Entity\Page\Column;
use Addiks\PHPSQL\Entity;
use Addiks\PHPSQL\Tool\CustomIterator;
use Addiks\PHPSQL\Entity\Storage;

class TableSchema extends Entity implements TableSchemaInterface
{

    private $databaseSchema;

    public function setDatabaseSchema(SchemaInterface $schema)
    {
        $this->databaseSchema = $schema;
    }

    public function getDatabaseSchema()
    {
        return $this->databaseSchema;
    }

    public function __construct(Storage $columnStorage, Storage $indexStorage)
    {

        $this->columnStorage = $columnStorage;
        $this->indexStorage = $indexStorage;
    }

    /**
     * Storage containing information about the columns.
     * @var Storage
     */
    private $columnStorage;

    /**
     * @return Storage
     */
    public function getColumnStorage()
    {
        return $this->columnStorage;
    }

    /**
     * Storage containing information about the indicies.
     * @var Storage
     */
    private $indexStorage;

    /**
     * @return Storage
     */
    public function getIndexStorage()
    {
        return $this->indexStorage;
    }

    public function setIndexStorage(Storage $indexStorage)
    {
        $this->indexStorage = $indexStorage;
    }

    public function getIndexIterator()
    {

        $storage = $this->getIndexStorage();

        $iteratorEntity = new Index();

        return new CustomIterator(null, [
            'valid' => function () use ($storage) {
                $data = fread($storage->getHandle(), Index::PAGE_SIZE);
                fseek($storage->getHandle(), 0-Index::PAGE_SIZE, SEEK_CUR);
                return strlen($data) === Index::PAGE_SIZE;
            },
            'rewind' => function () use ($storage) {
                fseek($storage->getHandle(), 0, SEEK_SET);
            },
            'key' => function () use ($storage) {
                return (ftell($storage->getHandle()) / Index::PAGE_SIZE);
            },
            'current' => function () use ($storage, $iteratorEntity) {
                $data = fread($storage->getHandle(), Index::PAGE_SIZE);
                fseek($storage->getHandle(), 0-Index::PAGE_SIZE, SEEK_CUR);
                $iteratorEntity->setData($data);
                $iteratorEntity->setId(ftell($storage->getHandle()) / Index::PAGE_SIZE);
                return $iteratorEntity;
            },
            'next' => function () use ($storage) {
                fseek($storage->getHandle(), Index::PAGE_SIZE, SEEK_CUR);
            }
        ]);
    }
    
    public function getIndexIdByColumns($columnIds)
    {
        
        foreach ($this->getIndexIterator() as $indexId => $indexPage) {
            /* @var $indexPage Index */
            
            if (count($indexPage->getColumns()) !== count($columnIds)) {
                continue;
            }
            
            foreach ($indexPage->getColumns() as $columnId) {
                if (!in_array($columnId, $columnIds)) {
                    continue 2;
                }
            }
            
            return $indexId;
        }
            
        return null;
    }
    
    public function indexExist($name)
    {
        
        foreach ($this->getIndexIterator() as $indexId => $indexPage) {
            /* @var $indexPage Index */
            
            if ($indexPage->getName()) {
                return true;
            }
        }
        
        return false;
    }

    public function addColumnPage(Column $column)
    {

        if ($this->getColumnStorage()->getLength()<=0) {
            $writeIndex = 0;
        } else {
            $writeIndex = $this->getLastIndex()+1;
        }

        $this->writeColumn($writeIndex, $column);
        
        return $writeIndex;
    }

    public function addIndexPage(Index $indexPage)
    {

        $storage = $this->getIndexStorage();

        $handle = $storage->getHandle();

        fseek($handle, 0, SEEK_END);

        $index = ftell($handle) / Index::PAGE_SIZE;

        fwrite($handle, $indexPage->getData());

        return $index;
    }

    public function getIndexPage($index)
    {

        $storage = $this->getIndexStorage();

        $handle = $storage->getHandle();

        fseek($handle, $index*Index::PAGE_SIZE, SEEK_SET);

        $data = fread($storage->getHandle(), Index::PAGE_SIZE);

        if (strlen($data) !== Index::PAGE_SIZE) {
            return null;
        }

        $indexPage = new Index();
        $indexPage->setData($data);
        $indexPage->setId($index);

        return $indexPage;
    }

    public function getColumnIterator()
    {

        $storage = $this->getColumnStorage();

        $iteratorEntity = new Column();

        return new CustomIterator(null, [
            'rewind' => function () use ($storage) {
                fseek($storage->getHandle(), 0, SEEK_SET);
            },
            'valid' => function () use ($storage) {

                $beforeSeek = ftell($storage->getHandle());
                $data = fread($storage->getHandle(), Column::PAGE_SIZE);
                fseek($storage->getHandle(), $beforeSeek, SEEK_SET);

                return strlen($data) === Column::PAGE_SIZE;
            },
            'key' => function () use ($storage) {

                return (ftell($storage->getHandle()) / Column::PAGE_SIZE);
            },
            'current' => function () use ($storage, $iteratorEntity) {
                $beforeSeek = ftell($storage->getHandle());
                $data = fread($storage->getHandle(), Column::PAGE_SIZE);
                fseek($storage->getHandle(), $beforeSeek, SEEK_SET);
                if (strlen($data)!==Column::PAGE_SIZE) {
                    return null;
                }
                $iteratorEntity->setData($data);
                $iteratorEntity->setId(ftell($storage->getHandle()) / Column::PAGE_SIZE);

                return clone $iteratorEntity;
            },
            'next' => function () use ($storage) {

                do {
                    fseek($storage->getHandle(), Column::PAGE_SIZE, SEEK_CUR);
                    
                    $checkData = fread($storage->getHandle(), Column::PAGE_SIZE);
                    fseek($storage->getHandle(), 0-strlen($checkData), SEEK_CUR);
                    
                } while (trim($checkData, "\0")==='' && strlen($checkData) === Column::PAGE_SIZE);
            }
        ]);
    }

    private $columnIdCache;

    public function getCachedColumnIds()
    {

        if (is_null($this->columnIdCache)) {
            $this->columnIdCache = array();
            $iterator = $this->getColumnIterator();
            $iterator->rewind();
            while ($iterator->valid()) {
                $this->columnIdCache[] = $iterator->key();
                $iterator->next();
            }
        }
        return $this->columnIdCache;
    }

    public function getPrimaryKeyColumns()
    {

        $primaryColumns = array();

        foreach ($this->getColumnIterator() as $columnId => $columnPage) {
            /* @var $columnPage Column */

            if ($columnPage->isPrimaryKey()) {
                $primaryColumns[$columnId] = $columnPage;
            }
        }

        return $primaryColumns;
    }

    public function getLastIndex()
    {
        $storage = $this->getColumnStorage();
        $position = ftell($storage->getHandle());

        fseek($storage->getHandle(), 0, SEEK_END);
        $endPosition = ftell($storage->getHandle());

        if ($endPosition === 0) {
            return null;
        }

        fseek($storage->getHandle(), $position, SEEK_SET);

        return (int)(($endPosition) / Column::PAGE_SIZE) -1;
    }

    public function listColumns()
    {

        $columns = array();
        foreach ($this->getColumnIterator() as $index => $columnPage) {
            /* @var $columnPage Column */

            $columns[$index] = clone $columnPage;
            
            $this->columnCache[$index] = $columns[$index];
        }

        return $columns;
    }

    private $columnCache = array();
    
    public function getColumnCache()
    {
        return $this->columnCache;
    }
    
    public function dropColumnCache()
    {
        $this->columnCache = array();
    }
    
    public function getColumn($index)
    {

        if (is_string($index)) {
            $index = $this->getColumnIndex($index);
        }
        
        if (!isset($this->columnCache[$index])) {
            $storage = $this->getColumnStorage();
            
            $position = $index * Column::PAGE_SIZE;
            
            fseek($storage->getHandle(), $position, SEEK_SET);
            $data = fread($storage->getHandle(), Column::PAGE_SIZE);
            
            if (strlen($data) !== Column::PAGE_SIZE) {
                return null;
            }
            
            $column = new Column();
            $column->setData($data);
            $column->setId($index);
            
            $this->columnCache[$index] = $column;
        }
        
        return $this->columnCache[$index];
    }

    public function columnExist($columnName)
    {
        return !is_null($this->getColumnIndex($columnName));
    }

    public function getColumnIndex($columnName)
    {

        foreach ($this->columnCache as $index => $columnPage) {
            /* @var $columnPage Column */

            if ($columnPage->getName() === $columnName) {
                return $index;
            }
        }
        
        foreach ($this->getColumnIterator() as $index => $columnPage) {
            /* @var $columnPage Column */

            if ($columnPage->getName() === $columnName) {
                return $index;
            }
        }
    }

    public function writeColumn($index = null, Column $column)
    {

        if (is_null($index)) {
            if (is_null($this->getLastIndex())) {
                $index = 0;
            } else {
                $index = $this->getLastIndex()+1;
            }
        }
        
        $this->columnCache[$index] = $column;
        
        $this->columnCache[$index] = $column;
        
        $storage = $this->getColumnStorage();

        flock($storage->getHandle(), LOCK_EX);
        fseek($storage->getHandle(), Column::PAGE_SIZE * $index, SEEK_SET);

        fwrite($storage->getHandle(), $column->getData());

        flock($storage->getHandle(), LOCK_UN);
    }
    
    public function removeColumn($index)
    {
        
        $storage = $this->getColumnStorage();
        $handle = $storage->getHandle();
        
        flock($handle, LOCK_EX);
        fseek($handle, Column::PAGE_SIZE * $index, SEEK_SET);
        
        fwrite($handle, str_pad("", Column::PAGE_SIZE, "\0"));
        
        flock($handle, LOCK_UN);
    }

    private $sizeCache;

    private $columnPositionCache = array();

    public function getRowPageSize()
    {

        if (is_null($this->sizeCache)) {
            $this->sizeCache = 0;
            $this->columnPositionCache = array();

            foreach ($this->getColumnIterator() as $columnPage) {
                /* @var $columnPage Column */

                $this->columnPositionCache[$columnPage->getId()] = $this->sizeCache;

                $this->sizeCache += $columnPage->getCellSize();
            }

        }

        return $this->sizeCache;
    }

    /**
     * Gets the position in a row-page-data where a cell for a given column starts.
     * @param string
     */
    public function getCellPositionInPage($columnId)
    {

        if (count($this->columnPositionCache)<=0) {
            $this->getRowPageSize();
        }

        if (!isset($this->columnPositionCache[$columnId])) {
            throw new ErrorException("Tableschema has no column at index {$columnId}!");
        }

        return $this->columnPositionCache[$columnId];
    }
}

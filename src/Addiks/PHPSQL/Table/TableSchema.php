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

use ArrayIterator;
use Addiks\PHPSQL\Iterators\CustomIterator;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;
use Addiks\PHPSQL\Column\ColumnSchema;
use Addiks\PHPSQL\Index\IndexSchema;
use Addiks\PHPSQL\Database\DatabaseSchemaInterface;

class TableSchema implements TableSchemaInterface
{

    private $databaseSchema;

    public function setDatabaseSchema(DatabaseSchemaInterface $schema)
    {
        $this->databaseSchema = $schema;
    }

    public function getDatabaseSchema()
    {
        return $this->databaseSchema;
    }

    public function __construct(FileResourceProxy $columnFile, FileResourceProxy $indexFile)
    {

        $this->columnFile = $columnFile;
        $this->indexFile = $indexFile;
    }

    /**
     * File containing information about the columns.
     * @var FileResourceProxy
     */
    private $columnFile;

    /**
     * @return FileResourceProxy
     */
    public function getColumnFile()
    {
        return $this->columnFile;
    }

    /**
     * File containing information about the indicies.
     * @var FileResourceProxy
     */
    private $indexFile;

    /**
     * @return FileResourceProxy
     */
    public function getIndexFile()
    {
        return $this->indexFile;
    }

    public function setindexFile(FileResourceProxy $indexFile)
    {
        $this->indexFile = $indexFile;
    }

    public function getIndexIterator()
    {

        $file = $this->getIndexFile();

        $iteratorEntity = new IndexSchema();

        return new CustomIterator(null, [
            'valid' => function () use ($file) {
                $data = $file->read(IndexSchema::PAGE_SIZE);
                $file->seek(0-IndexSchema::PAGE_SIZE, SEEK_CUR);
                return strlen($data) === IndexSchema::PAGE_SIZE;
            },
            'rewind' => function () use ($file) {
                $file->seek(0, SEEK_SET);
            },
            'key' => function () use ($file) {
                return ($file->tell() / IndexSchema::PAGE_SIZE);
            },
            'current' => function () use ($file, $iteratorEntity) {
                $data = $file->read(IndexSchema::PAGE_SIZE);
                $file->seek(0-IndexSchema::PAGE_SIZE, SEEK_CUR);
                $iteratorEntity->setData($data);
                $iteratorEntity->setId($file->tell() / IndexSchema::PAGE_SIZE);
                return clone $iteratorEntity;
            },
            'next' => function () use ($file) {
                $file->seek(IndexSchema::PAGE_SIZE, SEEK_CUR);
            }
        ]);
    }
    
    public function getIndexIdByColumns($columnIds)
    {
        $indexId = null;
        
        foreach ($this->getIndexIterator() as $currentIndexId => $indexSchema) {
            /* @var $indexSchema IndexSchema */
            
            if (count($indexSchema->getColumns()) !== count($columnIds)) {
                continue;
            }
            
            foreach ($indexSchema->getColumns() as $columnId) {
                if (!in_array($columnId, $columnIds)) {
                    continue 2;
                }
            }
            
            $indexId = $currentIndexId;
        }
            
        return $indexId;
    }

    public function getIndexIdByName($name)
    {
        $indexId = null;
        
        foreach ($this->getIndexIterator() as $currentIndexId => $indexSchema) {
            /* @var $indexSchema IndexSchema */
            
            if ($indexSchema->getName() === $name) {
                $indexId = $currentIndexId;
            }
        }

        return $indexId;
    }
    
    public function indexExist($name)
    {
        
        foreach ($this->getIndexIterator() as $indexId => $indexSchema) {
            /* @var $indexSchema IndexSchema */
            
            if ($indexSchema->getName()) {
                return true;
            }
        }
        
        return false;
    }

    public function addColumnSchema(ColumnSchema $column)
    {
        assert(!$this->hasColumn($column));

        if ($this->getColumnFile()->getLength()<=0) {
            $writeIndex = 0;
        } else {
            $writeIndex = $this->getLastIndex()+1;
        }
        
        $column->setIndex($writeIndex);

        $this->writeColumn($writeIndex, $column);
        
        return $writeIndex;
    }

    public function addIndexSchema(IndexSchema $indexSchema)
    {

        $file = $this->getIndexFile();

        $file->seek(0, SEEK_END);

        $index = $file->tell() / IndexSchema::PAGE_SIZE;

        $file->write($indexSchema->getData());

        return $index;
    }

    public function getIndexSchema($index)
    {

        $file = $this->getIndexFile();

        $file->seek($index*IndexSchema::PAGE_SIZE, SEEK_SET);

        $data = $file->read(IndexSchema::PAGE_SIZE);

        if (strlen($data) !== IndexSchema::PAGE_SIZE) {
            return null;
        }

        $indexSchema = new IndexSchema();
        $indexSchema->setData($data);
        $indexSchema->setId($index);

        return $indexSchema;
    }

    public function getColumnIterator()
    {
        $file = $this->getColumnFile();

        $iteratorEntity = new ColumnSchema();

        $columnPages = array();

        $file->seek(0, SEEK_SET);
        while (strlen($data = $file->read(ColumnSchema::PAGE_SIZE)) === ColumnSchema::PAGE_SIZE) {
            $key = ($file->tell() / ColumnSchema::PAGE_SIZE) -1;

            if (trim($data, "\0") !== '') {
                $columnPage = new ColumnSchema();
                $columnPage->setData($data);

                $columnPages[$key] = $columnPage;
            }
        }

        uasort($columnPages, function($columnPageA, $columnPageB){
            return $columnPageA->getIndex() - $columnPageB->getIndex();
        });

        return new ArrayIterator($columnPages);
    }

    private $columnIdCache;

    public function getCachedColumnIds()
    {

        if (is_null($this->columnIdCache)) {
            $this->columnIdCache = array();
            $iterator = $this->getColumnIterator();
            $iterator->rewind();
            while ($iterator->valid()) {
                $this->columnIdCache[] = (int)$iterator->key();
                $iterator->next();
            }
        }
        return $this->columnIdCache;
    }

    public function getPrimaryKeyColumns()
    {

        $primaryColumns = array();

        foreach ($this->getColumnIterator() as $columnId => $columnPage) {
            /* @var $columnPage ColumnSchema */

            if ($columnPage->isPrimaryKey()) {
                $primaryColumns[$columnId] = $columnPage;
            }
        }

        return $primaryColumns;
    }

    public function getLastIndex()
    {
        $file = $this->getColumnFile();
        $position = $file->tell();

        $file->seek(0, SEEK_END);
        $endPosition = $file->tell();

        if ($endPosition === 0) {
            return null;
        }

        $file->seek($position, SEEK_SET);

        return (int)(($endPosition) / ColumnSchema::PAGE_SIZE) -1;
    }

    public function listColumns()
    {
        $columns = array();
        foreach ($this->getColumnIterator() as $index => $columnPage) {
            /* @var $columnPage ColumnSchema */

            $columns[$index] = clone $columnPage;

            assert(is_int($index) && $index >= 0);
            
            $this->columnCache[$index] = $columns[$index];
        }

        return $columns;
    }

    public function getColumnNames()
    {
        $columns = array();
        foreach ($this->listColumns() as $index => $columnPage) {
            /* @var $columnPage ColumnSchema */

            $columns[$index] = $columnPage->getName();
        }

        return $columns;
    }

    public function hasColumn($column)
    {
        $hasColumn = null;

        if (is_numeric($column)) {
            $hasColumn = in_array((int)$column, $this->getCachedColumnIds());

        } else {
            if ($column instanceof ColumnSchema) {
                $column = $column->getName();
            }

            $hasColumn = in_array($column, $this->getColumnNames());
        }

        return $hasColumn;
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
        if (!is_numeric($index)) {
            $index = $this->getColumnIndex($index);
        }
        
        assert(is_numeric($index) && $index >= 0);
        $index = (int)$index;

        if (!isset($this->columnCache[$index])) {
            $file = $this->getColumnFile();
            
            $position = $index * ColumnSchema::PAGE_SIZE;
            
            $file->seek($position, SEEK_SET);
            $data = $file->read(ColumnSchema::PAGE_SIZE);
            
            if (strlen($data) !== ColumnSchema::PAGE_SIZE) {
                return null;
            }
            
            $column = new ColumnSchema();
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
            /* @var $columnPage ColumnSchema */

            if ($columnPage->getName() === $columnName) {
                return $index;
            }
        }
        
        foreach ($this->getColumnIterator() as $index => $columnPage) {
            /* @var $columnPage ColumnSchema */

            if ($columnPage->getName() === $columnName) {
                return $index;
            }
        }
    }

    public function writeColumn($index = null, ColumnSchema $column)
    {

        if (is_null($index)) {
            assert(!$this->hasColumn($column));

            if (is_null($this->getLastIndex())) {
                $index = 0;
            } else {
                $index = $this->getLastIndex()+1;
            }

        } else {
            $columnId = $this->getColumnIndex($column->getName());
            assert(is_null($columnId) || ($columnId === $index));
        }
            
        assert(is_int($index) && $index >= 0);

        $this->columnCache[$index] = $column;
        
        $file = $this->getColumnFile();

        $file->lock(LOCK_EX);
        $file->seek(ColumnSchema::PAGE_SIZE * $index, SEEK_SET);

        $file->write($column->getData());

        $file->lock(LOCK_UN);

        return $index;
    }
    
    public function removeColumn($index)
    {
        
        $file = $this->getColumnFile();
        
        $file->lock(LOCK_EX);
        $file->seek(ColumnSchema::PAGE_SIZE * $index, SEEK_SET);
        
        $file->write(str_pad("", ColumnSchema::PAGE_SIZE, "\0"));
        
        $file->lock(LOCK_UN);
    }

    private $sizeCache;

    private $columnPositionCache = array();

    public function getRowPageSize()
    {

        if (is_null($this->sizeCache)) {
            $this->sizeCache = 0;
            $this->columnPositionCache = array();

            foreach ($this->getColumnIterator() as $columnPage) {
                /* @var $columnPage ColumnSchema */

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

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

use ArrayIterator;
use Addiks\PHPSQL\Entity\Page\SchemaPage\IndexPage;
use Addiks\PHPSQL\Entity\Page\ColumnPage;
use Addiks\PHPSQL\Iterators\CustomIterator;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;

class TableSchema implements TableSchemaInterface
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
    public function getindexFile()
    {
        return $this->indexFile;
    }

    public function setindexFile(FileResourceProxy $indexFile)
    {
        $this->indexFile = $indexFile;
    }

    public function getIndexIterator()
    {

        $file = $this->getindexFile();

        $iteratorEntity = new IndexPage();

        return new CustomIterator(null, [
            'valid' => function () use ($file) {
                $data = $file->read(IndexPage::PAGE_SIZE);
                $file->seek(0-IndexPage::PAGE_SIZE, SEEK_CUR);
                return strlen($data) === IndexPage::PAGE_SIZE;
            },
            'rewind' => function () use ($file) {
                $file->seek(0, SEEK_SET);
            },
            'key' => function () use ($file) {
                return ($file->tell() / IndexPage::PAGE_SIZE);
            },
            'current' => function () use ($file, $iteratorEntity) {
                $data = $file->read(IndexPage::PAGE_SIZE);
                $file->seek(0-IndexPage::PAGE_SIZE, SEEK_CUR);
                $iteratorEntity->setData($data);
                $iteratorEntity->setId($file->tell() / IndexPage::PAGE_SIZE);
                return $iteratorEntity;
            },
            'next' => function () use ($file) {
                $file->seek(IndexPage::PAGE_SIZE, SEEK_CUR);
            }
        ]);
    }
    
    public function getIndexIdByColumns($columnIds)
    {
        
        foreach ($this->getIndexIterator() as $indexId => $indexPage) {
            /* @var $indexPage IndexPage */
            
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
            /* @var $indexPage IndexPage */
            
            if ($indexPage->getName()) {
                return true;
            }
        }
        
        return false;
    }

    public function addColumnPage(ColumnPage $column)
    {

        if ($this->getColumnFile()->getLength()<=0) {
            $writeIndex = 0;
        } else {
            $writeIndex = $this->getLastIndex()+1;
        }
        
        $column->setIndex($writeIndex);

        $this->writeColumn($writeIndex, $column);
        
        return $writeIndex;
    }

    public function addIndexPage(IndexPage $indexPage)
    {

        $file = $this->getindexFile();

        $file->seek(0, SEEK_END);

        $index = $file->tell() / IndexPage::PAGE_SIZE;

        $file->write($indexPage->getData());

        return $index;
    }

    public function getIndexPage($index)
    {

        $file = $this->getindexFile();

        $file->seek($index*IndexPage::PAGE_SIZE, SEEK_SET);

        $data = $file->read(IndexPage::PAGE_SIZE);

        if (strlen($data) !== IndexPage::PAGE_SIZE) {
            return null;
        }

        $indexPage = new IndexPage();
        $indexPage->setData($data);
        $indexPage->setId($index);

        return $indexPage;
    }

    public function getColumnIterator()
    {
        $file = $this->getColumnFile();

        $iteratorEntity = new ColumnPage();

        $columnPages = array();

        $file->seek(0, SEEK_SET);
        while (strlen($data = $file->read(ColumnPage::PAGE_SIZE)) === ColumnPage::PAGE_SIZE) {
            $key = ($file->tell() / ColumnPage::PAGE_SIZE) -1;

            if (trim($data, "\0") !== '') {
                $columnPage = new ColumnPage();
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
            /* @var $columnPage ColumnPage */

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

        return (int)(($endPosition) / ColumnPage::PAGE_SIZE) -1;
    }

    public function listColumns()
    {

        $columns = array();
        foreach ($this->getColumnIterator() as $index => $columnPage) {
            /* @var $columnPage ColumnPage */

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
            $file = $this->getColumnFile();
            
            $position = $index * ColumnPage::PAGE_SIZE;
            
            $file->seek($position, SEEK_SET);
            $data = $file->read(ColumnPage::PAGE_SIZE);
            
            if (strlen($data) !== ColumnPage::PAGE_SIZE) {
                return null;
            }
            
            $column = new ColumnPage();
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
            /* @var $columnPage ColumnPage */

            if ($columnPage->getName() === $columnName) {
                return $index;
            }
        }
        
        foreach ($this->getColumnIterator() as $index => $columnPage) {
            /* @var $columnPage ColumnPage */

            if ($columnPage->getName() === $columnName) {
                return $index;
            }
        }
    }

    public function writeColumn($index = null, ColumnPage $column)
    {

        if (is_null($index)) {
            if (is_null($this->getLastIndex())) {
                $index = 0;
            } else {
                $index = $this->getLastIndex()+1;
            }
        }
        
        $this->columnCache[$index] = $column;
        
        $file = $this->getColumnFile();

        $file->lock(LOCK_EX);
        $file->seek(ColumnPage::PAGE_SIZE * $index, SEEK_SET);

        $file->write($column->getData());

        $file->lock(LOCK_UN);

        return $index;
    }
    
    public function removeColumn($index)
    {
        
        $file = $this->getColumnFile();
        
        $file->lock(LOCK_EX);
        $file->seek(ColumnPage::PAGE_SIZE * $index, SEEK_SET);
        
        $file->write(str_pad("", ColumnPage::PAGE_SIZE, "\0"));
        
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
                /* @var $columnPage ColumnPage */

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

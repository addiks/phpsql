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

namespace Addiks\PHPSQL\Entity\TableSchema\Meta;

use ErrorException;
use Addiks\PHPSQL\Index\IndexInterface;
use Addiks\PHPSQL\Entity\SchemaInterface;

class Indicies implements IndexInterface
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
    
    private $keyLength;
    
    public function getKeyLength()
    {
        return $this->keyLength;
    }
    
    public function setKeyLength($keyLength)
    {
        $this->keyLength = $keyLength;
    }
    
    ### COLUMNS
    
    private $columns;
    
    protected function getInternalColumns()
    {
        if (is_null($this->columns)) {
            $this->columns = array();
            
            $keyLength = $this->getKeyLength();
            
            if (is_null($keyLength)) {
                throw new ErrorException("Cannot create meta-table-schema for indicies when no key-length is specified!");
            }
            
            $columnPage = new Column();
            for ($i=1; $i<=33; $i++) {
                $columnPage->setName("ref{$i}");
                $columnPage->setDataType(DataType::INT());
                $columnPage->setLength($keyLength);
                $columnPage->setExtraFlags(Column::EXTRA_PRIMARY_KEY);
                $this->columns[] = clone $columnPage;
                
                $columnPage->setName("val{$i}");
                $columnPage->setDataType(DataType::INT());
                $columnPage->setLength($keyLength);
                $columnPage->setExtraFlags(Column::EXTRA_PRIMARY_KEY);
                $this->columns[] = clone $columnPage;
                
                $columnPage->setName("row{$i}");
                $columnPage->setDataType(DataType::INT());
                $columnPage->setLength($keyLength);
                $columnPage->setExtraFlags(Column::EXTRA_PRIMARY_KEY);
                $this->columns[] = clone $columnPage;
            }
            
        }
        return $this->columns;
    }
    
    public function getColumnIterator()
    {
        return $this->getInternalColumns();
    }
    
    public function getColumnIndex($columnName)
    {
        foreach ($this->getInternalColumns() as $index => $columnPage) {
            if ($columnPage->getName() === $columnName) {
                return $index;
            }
        }
    }
    
    public function getCachedColumnIds()
    {
        return array_keys($this->getInternalColumns());
    }
    
    public function dropColumnCache()
    {
    }
    
    public function getPrimaryKeyColumns()
    {
        
        return $this->getInternalColumns();
    }
    
    public function listColumns()
    {
        return $this->getColumnIterator();
    }
    
    public function getColumn($index)
    {
        return $this->getInternalColumns()[$index];
    }
    
    public function columnExist($columnName)
    {
        return !is_null($this->getColumnIndex($columnName));
    }
    
    ### INDICIES
    
    public function getIndexIterator()
    {
    }
    
    public function indexExist($name)
    {
    }
    
    public function getIndexIdByColumns($columnIds)
    {
    }
    
    public function addIndexSchema(Index $indexPage)
    {
    }
    
    public function getIndexSchema($index)
    {
    }
    
    public function getLastIndex()
    {
    }
    
    ### COLUMNS MODIFIERS
    
    public function addColumnSchema(Column $column)
    {
    }
    
    public function writeColumn($index = null, Column $column)
    {
    }
}

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

namespace Addiks\PHPSQL\Table\Meta\InformationSchema;

use Addiks\PHPSQL\CustomIterator;
use Addiks\PHPSQL\Schema\SchemaManager;

class Table extends InformationSchema
{
    
    public function __construct(SchemaManager $schemaManager)
    {
        $this->schemaManager = $schemaManager;
    }

    private $schemaManager;

    public function getSchemaManager()
    {
        return $this->schemaManager;
    }

    private $internalTablesCache;
    
    protected function getInternalTables()
    {
        
        if (is_null($this->internalTablesCache)) {
            $this->internalTablesCache = array();
            
            foreach ($this->schemaManager->listSchemas() as $schemaId) {
                /* @var $schema Schema */
                $schema = $this->schemaManager->getSchema($schemaId);
                
                foreach ($schema->listTables() as $tableName) {
                    $this->internalTablesCache[] = "{$schemaId}.{$tableName}";
                }
            }
        }
        return $this->internalTablesCache;
    }
    
    private $internalTablesIterator;
    
    protected function getInternalTablesIterator()
    {
        if (is_null($this->internalTablesIterator)) {
            $this->internalTablesIterator = new \ArrayIterator($this->getInternalTables());
        }
        return $this->internalTablesIterator;
    }
    
    public function getTableSchema()
    {
        return $this->schemaManager->getTableSchema();
        # ?
    }
    
    public function addColumnDefinition(ColumnDefinition $columnDefinition)
    {
        
    }
    
    public function getCellData($rowId, $columnId)
    {
        
        $tableName = $this->getInternalTables()[$rowId];
        list($database, $tableName) = explode(".", $tableName);
        
        /* @var $schema InformationSchema */
        $schema = $this->schemaManager->getSchema($database);
        
        /* @var $schemaPage Schema */
        $schemaPage = $schema->getTablePage($tableName);
        
        /* @var $tableSchema TableSchema */
        $tableSchema = $this->schemaManager->getTableSchema($database, $tableName);
        
        switch($columnId){
            
            case 0: # TABLE_CATALOG
                return $database;
                
            case 1: # TABLE_SCHEMA
                return $tableName;
                
            case 2: # TABLE_NAME
                return $tableName;
            
            case 3: # TABLE_TYPE
                return 'BASE TABLE';
            
            case 4: # ENGINE
                return $schemaPage->getEngine();
            
            case 5: # VERSION
                return "1.0.0";
            
            case 6: # ROW_FORMAT
                return $schemaPage->getRowFormat();
            
            case 7: # TABLE_ROWS
                return 0;
                    
            case 8: # AVG_ROW_LENGTH
                return null;
                    
            case 9: # DATA_LENGTH
                return null;
                
            case 10: # MAX_DATA_LENGTH
                return null;
            
            case 11: # INDEX_LENGTH
                return null;
            
            case 12: # DATA_FREE
                return null;
                    
            case 13: # AUTO_INCREMENT
                return 0;
                    
            case 14: # CREATE_TIME
                return null;
                
            case 15: # UPDATE_TIME
                return null;
            
            case 16: # CHECK_TIME
                return null;
            
            case 17: # TABLE_COLLATION
                return null;
                    
            case 18: # CHECKSUM
                return null;
                    
            case 19: # CREATE_OPTIONS
                return null;
                
            case 20: # TABLE_COMMENT
                return null;
        }
    }
    
    public function getRowData($rowId = null)
    {
    
        if (is_null($rowId)) {
            $rowId = $this->getInternalTablesIterator()->key();
        }
        
        /* @var $tableSchema Tables */
        $tableSchema = $this->getTableSchema();
        
        $row = array();
        for ($columnId=0; $columnId<=20; $columnId++) {
            $row[$tableSchema->getColumn($columnId)->getName()] = $this->getCellData($rowId, $columnId);
        }
        return $row;
    }
    
    private $iterator;
    
    public function getIterator()
    {
    
        if (is_null($this->iterator)) {
            /* @var $iterator \ArrayIterator */
            $iterator = $this->getInternalTablesIterator();
            
            $table = $this;
            
            $this->iterator = new CustomIterator($iterator, [
                'current' => function ($currentValue) use ($iterator, $table) {
                    return $table->getRowData($iterator->key());
                }
            ]);
        }
        return $this->iterator;
    }
    
    public function getRowExists($rowId = null)
    {
        
        if (is_null($rowId)) {
            $rowId = $this->getInternalTablesIterator()->key();
        }
        
        return isset($this->getInternalTables()[$rowId]);
    }
    
    public function seek($rowId)
    {
        $this->getInternalTablesIterator()->seek($rowId);
    }
    
    public function count()
    {
        return count($this->getInternalTables());
    }
}

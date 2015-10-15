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

namespace Addiks\PHPSQL\Schema\Meta;

use Addiks\PHPSQL\Index\IndexInterface;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\Index;

class Indicies implements IndexInterface
{
    
    public function __construct(SchemaManager $schemaManager)
    {
        $this->schemaManager = $schemaManager;
    }

    protected $schemaManager;

    public function getSchemaManager()
    {
        return $this->schemaManager;
    }

    ### TABLES
    
    public function listTables()
    {
        
        $tables = array();
    
        foreach ($this->schemaManager->listSchemas() as $schemaId) {
            if ($this->schemaManager->isMetaSchema($schemaId)) {
                continue;
            }
            
            /* @var $schema Schema */
            $schema = $this->schemaManager->getSchema($schemaId);
            
            foreach ($schema->listTables() as $tableName) {
                /* @var $tableSchema TableSchema */
                $tableSchema = $this->schemaManager->getTableSchema($tableName, $schemaId);
                
                foreach ($tableSchema->getIndexIterator() as $indexPage) {
                    /* @var $indexPage Index */
                    
                    $tables[] = "{$schemaId}__{$tableName}__{$indexPage->getName()}";
                }
                
            }
            
        }
        
        return $tables;
    }
    
    public function tableExists($tableName)
    {
        
        $tableNameParts = explode("__", $tableName);
        
        if (count($tableNameParts)!==3) {
            return false;
        }
        
        list($schemaId, $tableName, $indexId) = $tableNameParts;
        
        /* @var $tableSchema TableSchema */
        $tableSchema = $this->schemaManager->getTableSchema($tableName, $schemaId);
        
        return $tableSchema->indexExist($indexId);
        
    }
    
    public function getTableIndex($tableName)
    {
        
        return array_search($tableName, $this->listTables());
    }
    
    public function registerTable($tableName)
    {
    }
    
    public function registerTableSchema(Schema $schemaPage)
    {
    }
    
    public function unregisterTable($tableName)
    {
    }
    
    public function getTablePage($tableId)
    {
        
        $entity = new Schema();
        $entity->setName($tableId);
        $entity->setCollation("latin1_bin");
        $entity->setEngine(Engine::MYISAM());
        $entity->setType(Type::TABLE());
        
        return $entity;
    }
    
    ### VIEWS
    
    public function listViews()
    {
    }
    
    public function viewExists($viewName)
    {
    }
    
    public function getViewIndex($viewName)
    {
    }
    
    public function registerView($viewName)
    {
    }
    
    public function unregisterView($viewName)
    {
    }
}

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

use Addiks\PHPSQL\Entity\Index\IndexInterface;

class Indicies implements IndexInterface
{
    
    ### TABLES
    
    public function listTables()
    {
        
        $tables = array();
    
        /* @var $database Database */
        $this->factorize($database);
        
        foreach ($database->listSchemas() as $schemaId) {
            if ($database->isMetaSchema($schemaId)) {
                continue;
            }
            
            /* @var $schema Schema */
            $schema = $database->getSchema($schemaId);
            
            foreach ($schema->listTables() as $tableName) {
                /* @var $tableSchema TableSchema */
                $tableSchema = $database->getTableSchema($tableName, $schemaId);
                
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
        
        /* @var $database Database */
        $this->factorize($database);
        
        /* @var $tableSchema TableSchema */
        $tableSchema = $database->getTableSchema($tableName, $schemaId);
        
        return $tableSchema->indexExist($indexId);
        
        /* @var $index Index */
        $this->factorize($index, [$indexId, $tableName, $schemaId]);
        
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

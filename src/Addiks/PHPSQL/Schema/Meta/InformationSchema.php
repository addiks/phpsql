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

class InformationSchema implements IndexInterface
{
    
    ### TABLES
    
    public function listTables()
    {
    
        $tables = array();
    
        $tables[] = "TABLES";
        
        return $tables;
    }
    
    public function tableExists($tableName)
    {
    
        return in_array($tableName, $this->listTables());
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
        
        switch($tableId){
            case 'TABLES':
                break;
        }
        
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

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

namespace Addiks\PHPSQL\Service\Executor;

use Addiks\PHPSQL\Value\Enum\Sql\Show\ShowType;

use Addiks\PHPSQL\Entity\Result\Temporary;
use Addiks\PHPSQL\Resource\Database;
use Addiks\PHPSQL\Service\Executor;
use Addiks\PHPSQL\Entity\Job\Statement\ShowStatement as ShowJob;
use Addiks\PHPSQL\Resource\StoragesProxyTrait;

class ShowExecutor extends Executor
{
    
    use StoragesProxyTrait;
    
    protected function executeConcreteJob($statement, array $parameters = array())
    {
        /* @var $statement Show */
        
        switch($statement->getType()){
            case ShowType::DATABASES():
                return $this->executeShowDatabases($statement, $parameters);
                
            case ShowType::TABLES():
                return $this->executeShowTables($statement, $parameters);
                
            case ShowType::VIEWS():
                return $this->executeShowViews($statement, $parameters);
                
            case ShowType::COLUMNS():
                return $this->executeShowColumns($statement, $parameters);
        }
    }
    
    protected function executeShowColumns(ShowJob $statement, array $parameters = array())
    {
    
    }
    
    protected function executeShowViews(ShowJob $statement, array $parameters = array())
    {
    
    }
    
    protected function executeShowTables(ShowJob $statement, array $parameters = array())
    {
        
        /* @var $databaseResource Database */
        $this->factorize($databaseResource);
            
        /* @var $result Temporary */
        $this->factorize($result, [['TABLE']]);
        
        $list = $databaseResource->getSchema($statement->getDatabase())->listTables();
        sort($list);
        
        foreach ($list as $tableName) {
            $result->addRow([$tableName]);
        }
        
        return $result;
    }
    
    ### DATABASE ###
    
    protected function executeShowDatabases(ShowJob $statement, array $parameters = array())
    {
        
        /* @var $databaseResource Database */
        $this->factorize($databaseResource);
            
        /* @var $result Temporary */
        $this->factorize($result, [['DATABASE']]);
        
        $list = $databaseResource->listSchemas();
        sort($list);
        
        foreach ($list as $schemaId) {
            $result->addRow([$schemaId]);
        }
        
        return $result;
    }
}

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

use Addiks\PHPSQL\Value\Enum\Sql\Alter\DataChange\AlterAttributeType;

use Addiks\PHPSQL\Entity\Result\Temporary;

use Addiks\PHPSQL\Resource\Database;

class AlterExecutor extends Executor
{
    
    protected function executeConcreteJob($statement, array $parameters = array())
    {
        /* @var $statement Alter */
        
        /* @var $databaseResource Database */
        $this->factorize($databaseResource);
        
        /* @var $tableSpecifier TableSpecifier */
        $tableSpecifier = $statement->getTable();
        
        /* @var $tableResource Table */
        $this->factorize($tableResource, [$tableSpecifier->getTable(), $tableSpecifier->getDatabase()]);
        
        /* @var $tableSchema TableSchema */
        $tableSchema = $tableResource->getTableSchema();
        
        foreach ($statement->getDataChanges() as $dataChange) {
            /* @var $dataChange DataChange */
            
            switch($dataChange->getAttribute()){
                
                case AlterAttributeType::ADD():
                    
                    /* @var $columnDefinition ColumnDefinition */
                    $columnDefinition = $dataChange->getSubject();
                    
                    $tableResource->addColumnDefinition($columnDefinition);
                    break;
                    
                case AlterAttributeType::DROP():
                    
                    /* @var $columnSpecifier Column */
                    $columnSpecifier = $dataChange->getSubject();
                    
                    $columnId = $tableSchema->getColumnIndex($columnSpecifier->getColumn());
                    
                    $tableSchema->removeColumn($columnId);
                    
                    
                    
                    break;
                        
                case AlterAttributeType::MODIFY():
                    break;
                    
                case AlterAttributeType::RENAME():
                    break;
                    
                case AlterAttributeType::CHARACTER_SET():
                    break;
                    
                case AlterAttributeType::COLLATE():
                    break;
                    
                case AlterAttributeType::CONVERT():
                    break;
                
                case AlterAttributeType::DEFAULT_VALUE():
                    break;
                    
                case AlterAttributeType::ORDER_BY_ASC():
                    break;
                
                case AlterAttributeType::ORDER_BY_DESC():
                    break;
                        
                case AlterAttributeType::SET_AFTER():
                    break;
                    
                case AlterAttributeType::SET_FIRST():
                    break;
                        
            }
        }
        
        /* @var $result Temporary */
        $this->factorize($result);
        
        return $result;
    }
}

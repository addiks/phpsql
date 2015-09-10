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

namespace Addiks\PHPSQL\StatementExecutor;

use Addiks\PHPSQL\Value\Enum\Sql\Alter\DataChange\AlterAttributeType;
use Addiks\PHPSQL\Entity\Result\Temporary;
use Addiks\PHPSQL\Database;
use Addiks\PHPSQL\Filesystem\FilesystemInterface;
use Addiks\PHPSQL\ValueResolver;
use Addiks\PHPSQL\TableManager;
use Addiks\PHPSQL\Entity\Result\TemporaryResult;
use Addiks\PHPSQL\Entity\Job\Statement\AlterStatement;
use Addiks\PHPSQL\Entity\Job\StatementJob;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\Entity\ExecutionContext;
use Addiks\PHPSQL\Entity\Job\Part\ColumnDefinition;
use Addiks\PHPSQL\Table;

class AlterExecutor implements StatementExecutorInterface
{
    
    public function __construct(
        SchemaManager $schemaManager,
        TableManager $tableManager
    ) {
        $this->schemaManager = $schemaManager;
        $this->tableManager = $tableManager;
    }

    protected $schemaManager;

    public function getSchemaManager()
    {
        return $this->schemaManager;
    }

    protected $tableManager;

    public function getTableManager()
    {
        return $this->tableManager;
    }
    
    public function canExecuteJob(StatementJob $statement)
    {
        return $statement instanceof AlterStatement;
    }

    public function executeJob(StatementJob $statement, array $parameters = array())
    {
        /* @var $statement AlterStatement */
        
        $executionContext = new ExecutionContext(
            $this->schemaManager,
            $statement,
            $parameters
        );

        /* @var $tableSpecifier TableSpecifier */
        $tableSpecifier = $statement->getTable();
        
        /* @var $tableResource Table */
        $tableResource = $this->tableManager->getTable(
            $tableSpecifier->getTable(),
            $tableSpecifier->getDatabase()
        );

        /* @var $tableSchema TableSchema */
        $tableSchema = $tableResource->getTableSchema();
        
        foreach ($statement->getDataChanges() as $dataChange) {
            /* @var $dataChange DataChange */
            
            switch($dataChange->getAttribute()){
                
                case AlterAttributeType::ADD():
                    /* @var $columnDefinition ColumnDefinition */
                    $columnDefinition = $dataChange->getSubject();
                    
                    $tableResource->addColumnDefinition($columnDefinition, $executionContext);
                    break;
                    
                case AlterAttributeType::DROP():
                    /* @var $columnSpecifier ColumnSpecifier */
                    $columnSpecifier = $dataChange->getSubject();
                    
                    $columnId = $tableSchema->getColumnIndex($columnSpecifier->getColumn());
                    
                    $tableSchema->removeColumn($columnId);
                    break;
                        
                case AlterAttributeType::MODIFY():
                    /* @var $columnDefinition ColumnDefinition */
                    $columnDefinition = $dataChange->getSubject();
                    
                    $tableResource->modifyColumnDefinition($columnDefinition, $executionContext);
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
        
        $result = new TemporaryResult();
        
        return $result;
    }
}

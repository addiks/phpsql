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

use Addiks\PHPSQL\Executor;
use Addiks\PHPSQL\Entity\Result\Temporary;
use Addiks\PHPSQL\Database;
use Addiks\PHPSQL\Entity\Result\TemporaryResult;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\Entity\Job\StatementJob\DescribeStatement;
use Addiks\PHPSQL\Entity\Job\StatementJob;
use Addiks\PHPSQL\Filesystem\FilesystemInterface;
use Addiks\PHPSQL\ValueResolver;

class DescribeExecutor implements StatementExecutorInterface
{
    
    public function __construct(
        SchemaManager $schemaManager
    ) {
        $this->schemaManager = $schemaManager;
    }

    protected $schemaManager;

    public function getSchemaManager()
    {
        return $this->schemaManager;
    }

    public function canExecuteJob(StatementJob $statement)
    {
        return $statement instanceof DescribeStatement;
    }

    public function executeJob(StatementJob $statement, array $parameters = array())
    {
        /* @var $statement DescribeStatement */
        
        $result = new TemporaryResult(['Field', 'Type', 'Null', 'Key', 'Default', 'Extra']);
        
        /* @var $tableSchema TableSchema */
        $tableSchema = $this->schemaManager->getTableSchema($statement->getTable()->getTable(), $statement->getTable()->getDatabase());
        
        if (is_null($tableSchema)) {
            throw new InvalidArgument("Table '{$statement->getTable()}' not found!");
        }
        
        foreach ($tableSchema->getColumnIterator() as $columnId => $columnPage) {
            /* @var $columnPage Column */
            
            $fieldName = $columnPage->getName();
            
            $dataType = $columnPage->getDataType();
            $length = $columnPage->getLength();
            
            $typeString = "{$dataType->getName()}({$length})";
            
            $null = $columnPage->isNotNull() ?'NO' :'YES';
            
            $key = $columnPage->isPrimaryKey() ?'PRI' :($columnPage->isUniqueKey() ?'UNQ' :'MUL');
                
            $default = "";
            
            $extraArray = array();
            
            if ($columnPage->isAutoIncrement()) {
                $extraArray[] = "auto_increment";
            }
            
            $result->addRow([$fieldName, $typeString, $null, $key, $default, implode(" ", $extraArray)]);
        }
        
        return $result;
    }
}

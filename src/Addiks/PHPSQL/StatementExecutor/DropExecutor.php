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
use Addiks\PHPSQL\Result\Temporary;
use Addiks\PHPSQL\Database\Database;
use Addiks\PHPSQL\Job\StatementJob;
use Addiks\PHPSQL\Filesystem\FilesystemInterface;
use Addiks\PHPSQL\ValueResolver\ValueResolver;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\Job\Statement\DropStatement;
use Addiks\PHPSQL\StatementExecutor\ExecutionContext;
use Addiks\PHPSQL\Result\TemporaryResult;

class DropExecutor implements StatementExecutorInterface
{
    
    public function __construct(
        SchemaManager $schemaManager,
        ValueResolver $valueResolver
    ) {
        $this->schemaManager = $schemaManager;
        $this->valueResolver = $valueResolver;
    }

    protected $schemaManager;

    public function getSchemaManager()
    {
        return $this->schemaManager;
    }

    protected $valueResolver;
    
    public function canExecuteJob(StatementJob $statement)
    {
        return $statement instanceof DropStatement;
    }

    public function executeJob(StatementJob $statement, array $parameters = array())
    {
        /* @var $statement DropStatement */

        $context = new ExecutionContext(
            $this->schemaManager,
            $statement,
            $parameters
        );
        
        switch($statement->getType()){
            case DropStatement::TYPE_DATABASE:
                return $this->executeDropDatabase($statement, $context);
                
            case DropStatement::TYPE_TABLE:
                return $this->executeDropTable($statement, $context);
                
            case DropStatement::TYPE_VIEW:
                return $this->executeDropView($statement, $context);
        }
        
    }
    
    protected function executeDropDatabase(DropStatement $statement, ExecutionContext $context)
    {
        
        /* @var $databaseSchema Schema */
        $databaseSchema = $this->schemaManager->getSchema();
        
        foreach ($statement->getSubjects() as $subjectValue) {
            $subject = $this->valueResolver->resolveValue($subjectValue, $context);
            $this->schemaManager->removeSchema($subject);
        }
        
        ### RESULT
        
        $result = new TemporaryResult();
        $result->setIsSuccess(true);
        
        foreach ($statement->getSubjects() as $subjectValue) {
            $subject = $this->valueResolver->resolveValue($subjectValue, $context);
            if ($this->schemaManager->schemaExists($subject)) {
                $result->setIsSuccess(false);
                break;
            }
        }
        
        return $result;
    }
    
    protected function executeDropTable(DropStatement $statement, ExecutionContext $context)
    {

        foreach ($statement->getSubjects() as $tableNameValue) {
            $tableName = $this->valueResolver->resolveValue($tableNameValue, $context);
            $this->schemaManager->dropTable($tableName);
        }
        
        $databaseSchema = $this->schemaManager->getSchema();
        
        $result = new TemporaryResult();
        $result->setIsSuccess(!$databaseSchema->tableExists($tableName));
        
        return $result;
    }
    
    protected function executeDropView(DropStatement $statement, ExecutionContext $context)
    {
        
        $result = new TemporaryResult();
        return $result;
    }
}

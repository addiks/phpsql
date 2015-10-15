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
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\Job\Statement\UseStatement;
use Addiks\PHPSQL\ValueResolver\ValueResolver;
use Addiks\PHPSQL\StatementExecutor\ExecutionContext;
use Addiks\PHPSQL\Result\TemporaryResult;

class UseExecutor implements StatementExecutorInterface
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
    
    public function canExecuteJob(StatementJob $statement)
    {
        return $statement instanceof UseStatement;
    }

    public function executeJob(StatementJob $statement, array $parameters = array())
    {
        /* @var $statement UseStatement */

        $executionContext = new ExecutionContext(
            $this->schemaManager,
            $statement,
            $parameters
        );
        
        $databaseValue = $statement->getDatabase();

        $databaseName = $this->valueResolver->resolveValue($databaseValue, $executionContext);

        $this->schemaManager->setCurrentlyUsedDatabaseId($databaseName);
        
        $result = new TemporaryResult();
        $result->setIsSuccess($this->schemaManager->getCurrentlyUsedDatabaseId() === $databaseName);
        
        return $result;
    }
}

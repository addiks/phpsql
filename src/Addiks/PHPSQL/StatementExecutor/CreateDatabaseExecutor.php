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

use Addiks\PHPSQL\StatementExecutor\StatementExecutor;
use Addiks\PHPSQL\Result\Temporary;
use Addiks\PHPSQL\Database\Database;
use Addiks\PHPSQL\Job\Statement\Create\CreateDatabaseStatement;
use Addiks\PHPSQL\Job\StatementJob;
use Addiks\PHPSQL\ValueResolver\ValueResolver;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\Result\TemporaryResult;
use Addiks\PHPSQL\StatementExecutor\ExecutionContext;

class CreateDatabaseExecutor implements StatementExecutorInterface
{

    public function __construct(
        ValueResolver $valueResolver,
        SchemaManager $schemaManager
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

    public function getValueResolver()
    {
        return $this->valueResolver;
    }

    public function canExecuteJob(StatementJob $statement)
    {
        return $statement instanceof CreateDatabaseStatement;
    }

    public function executeJob(StatementJob $statement, array $parameters = array())
    {
        /* @var $statement CreateDatabaseStatement */

        $executionContext = new ExecutionContext(
            $this->schemaManager,
            $statement,
            $parameters
        );

        $name = $this->valueResolver->resolveValue($statement->getName(), $executionContext);

        $this->schemaManager->createSchema($name);

        ### CREATE RESULTSET

        $result = new TemporaryResult();
        $result->setIsSuccess($this->schemaManager->schemaExists($name));

        return $result;
    }
}

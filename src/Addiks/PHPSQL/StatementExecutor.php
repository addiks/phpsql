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

namespace Addiks\PHPSQL;

use Addiks\PHPSQL\StatementExecutor\StatementExecutorInterface;
use Addiks\PHPSQL\StatementExecutor\CreateDatabaseExecutor;
use Addiks\PHPSQL\StatementExecutor\AlterExecutor;
use Addiks\PHPSQL\StatementExecutor\CreateIndexExecutor;
use Addiks\PHPSQL\StatementExecutor\CreateTableExecutor;
use Addiks\PHPSQL\StatementExecutor\DeleteExecutor;
use Addiks\PHPSQL\StatementExecutor\DescribeExecutor;
use Addiks\PHPSQL\StatementExecutor\DropExecutor;
use Addiks\PHPSQL\StatementExecutor\InsertExecutor;
use Addiks\PHPSQL\StatementExecutor\SelectExecutor;
use Addiks\PHPSQL\StatementExecutor\SetExecutor;
use Addiks\PHPSQL\StatementExecutor\ShowExecutor;
use Addiks\PHPSQL\StatementExecutor\UpdateExecutor;
use Addiks\PHPSQL\StatementExecutor\UseExecutor;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\TableManager;
use Addiks\PHPSQL\ValueResolver;
use Addiks\PHPSQL\Entity\Job\StatementJob;

class StatementExecutor implements StatementExecutorInterface
{
    public function __construct(
        SchemaManager $schemaManager,
        TableManager $tableManager,
        ValueResolver $valueResolver,
        $doInitStatementExecutors = true
    ) {
        $this->schemaManager = $schemaManager;
        $this->tableManager = $tableManager;
        $this->valueResolver = $valueResolver;
        
        if ($doInitStatementExecutors) {
            $this->initStatementExecutors();
        }
    }

    public function canExecuteJob(StatementJob $statement)
    {
        return true;
    }

    public function executeJob(StatementJob $statement, array $parameters = array())
    {
        $result = null;

        foreach ($this->getStatementExecutors() as $statementExecutor) {
            /* @var $statementExecutor */

            if ($statementExecutor->canExecuteJob($statement)) {
                $result = $statementExecutor->executeJob($statement, $parameters);
                break;
            }
        }

        return $result;
    }

    protected $statementExecutors = array();

    public function getStatementExecutors()
    {
        return $this->statementExecutors;
    }

    public function addStatementExecutor(StatementExecutorInterface $statementExecutor)
    {
        $this->statementExecutors[] = $statementExecutor;
    }

    public function clearStatementExecutors()
    {
        $this->statementExecutors = array();
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

    protected $valueResolver;

    public function getValueResolver()
    {
        return $this->valueResolver;
    }

    public function initStatementExecutors()
    {

        $schemaManager = $this->schemaManager;
        $tableManager = $this->tableManager;
        $valueResolver = $this->valueResolver;
        $selectExecutor = new SelectExecutor(
            $tableManager->getFilesystem(),
            $schemaManager,
            $tableManager,
            $valueResolver
        );

        $this->addStatementExecutor(new AlterExecutor($schemaManager, $tableManager));
        $this->addStatementExecutor(new CreateDatabaseExecutor($valueResolver, $schemaManager));
        $this->addStatementExecutor(new CreateIndexExecutor($tableManager));
        $this->addStatementExecutor(new CreateTableExecutor($schemaManager, $tableManager, $valueResolver));
        $this->addStatementExecutor(new DeleteExecutor($valueResolver, $tableManager));
        $this->addStatementExecutor(new DescribeExecutor($schemaManager));
        $this->addStatementExecutor(new DropExecutor($schemaManager, $valueResolver));
        $this->addStatementExecutor(new InsertExecutor($valueResolver, $tableManager, $selectExecutor, $schemaManager));
        $this->addStatementExecutor($selectExecutor);
        $this->addStatementExecutor(new SetExecutor($valueResolver));
        $this->addStatementExecutor(new ShowExecutor($schemaManager));
        $this->addStatementExecutor(new UpdateExecutor($valueResolver, $schemaManager, $tableManager));
        $this->addStatementExecutor(new UseExecutor($schemaManager, $valueResolver));
    }
}

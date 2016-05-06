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
use Addiks\PHPSQL\StatementExecutor\StatementExecutorInterface;
use Addiks\PHPSQL\Job\Statement\UpdateStatement;
use Addiks\PHPSQL\Job\StatementJob;
use Addiks\PHPSQL\ValueResolver\ValueResolver;
use Addiks\PHPSQL\Table\TableManager;
use Addiks\PHPSQL\Result\TemporaryResult;
use Addiks\PHPSQL\StatementExecutor\ExecutionContext;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\Iterators\UsesBinaryDataInterface;

class UpdateExecutor implements StatementExecutorInterface
{

    public function __construct(
        ValueResolver $valueResolver,
        SchemaManager $schemaManager,
        TableManager $tableManager
    ) {
        $this->valueResolver = $valueResolver;
        $this->tableManager = $tableManager;
        $this->schemaManager = $schemaManager;
    }

    protected $valueResolver;

    public function getValueResolver()
    {
        return $this->valueResolver;
    }

    protected $tableManager;

    public function getTableManager()
    {
        return $this->tableManager;
    }

    protected $schemaManager;

    public function canExecuteJob(StatementJob $statement)
    {
        return $statement instanceof UpdateStatement;
    }

    public function executeJob(StatementJob $statement, array $parameters = array())
    {
        /* @var $statement UpdateStatement */

        $result = new TemporaryResult();
        // TODO: multiple tables or not?

        $executionContext = new ExecutionContext(
            $this->schemaManager,
            $statement,
            $parameters,
            $this->valueResolver
        );

        /* @var $tableSpecifier TableSpecifier */
        $tableSpecifier = $statement->getTables()[0];

        /* @var $tableResource Table */
        $tableResource = $this->tableManager->getTable(
            $tableSpecifier->getTable(),
            $tableSpecifier->getDatabase()
        );

        /* @var $tableSchema TableSchema */
        $tableSchema = $tableResource->getTableSchema();

        $indicies = array();
        foreach ($tableSchema->getIndexIterator() as $indexId => $indexPage) {
            /* @var $indexPage Index */

            /* @var $index Index */
            $index = $tableResource->getIndex(
                $indexPage->getName()
            );

            $indicies[] = $index;
        }

        /* @var $condition Value */
        $condition = $statement->getCondition();

        foreach ($tableResource as $rowId => $row) {
            if ($tableResource instanceof UsesBinaryDataInterface
            &&  $tableResource->usesBinaryData()) {
                $row = $tableResource->convertDataRowToStringRow($row);
            }

            $executionContext->setCurrentSourceRow($row);

            $conditionResult = $this->valueResolver->resolveValue($condition, $executionContext);

            if ($conditionResult) {
                $newRow = $row;
                foreach ($statement->getDataChanges() as $dataChange) {
                    /* @var $dataChange DataChange */

                    $columnName = (string)$dataChange->getColumn();

                    $newValue = $dataChange->getValue();
                    $newValue = $this->valueResolver->resolveValue($newValue, $executionContext);

                    $newRow[$columnName] = $newValue;
                }

                $row    = $tableResource->convertStringRowToDataRow($row);
                $newRow = $tableResource->convertStringRowToDataRow($newRow);

                foreach ($indicies as $index) {
                    /* @var $index Index */

                    $index->updateRow($row, $newRow, $rowId);
                }

                $tableResource->setRowData($rowId, $newRow);
            }
        }

        return $result;
    }
}

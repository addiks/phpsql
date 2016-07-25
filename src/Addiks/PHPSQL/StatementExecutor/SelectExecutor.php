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

use ErrorException;
use InvalidArgumentException;
use Addiks\PHPSQL\Database\Database;
use Addiks\PHPSQL\SelectResult;
use Addiks\PHPSQL\Executor;
use Addiks\PHPSQL\StatementExecutor\StatementExecutorInterface;
use Addiks\PHPSQL\Job\StatementJob;
use Addiks\PHPSQL\Job\Statement\SelectStatement;
use Addiks\PHPSQL\Filesystem\FilesystemInterface;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\ValueResolver\ValueResolver;
use Addiks\PHPSQL\Result\TemporaryResult;
use Addiks\PHPSQL\Table\TableManager;
use Addiks\PHPSQL\Table\TableContainer;
use Addiks\PHPSQL\Job\Part\ParenthesisPart;
use Addiks\PHPSQL\Value\Specifier\TableSpecifier;
use Addiks\PHPSQL\Table\TableInterface;
use Addiks\PHPSQL\Table\TableSchema;
use Addiks\PHPSQL\Entity\Page\ColumnSchema;
use Addiks\PHPSQL\Job\Part\ValuePart;
use Addiks\PHPSQL\Iterators\JoinIterator;
use Addiks\PHPSQL\StatementExecutor\ExecutionContext;
use Addiks\PHPSQL\Iterators\SortedResourceIterator;
use Addiks\PHPSQL\Iterators\FilteredResourceIterator;
use Addiks\PHPSQL\Job\Part\Join;
use Addiks\PHPSQL\Job\Part\Join\TableJoin;
use Addiks\PHPSQL\Job\Part\GroupingDefinition;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\Entity\Index\IndexInterface;
use Addiks\PHPSQL\Value\Enum\Sql\Operator;
use Addiks\PHPSQL\Job\Part;
use Addiks\PHPSQL\Job\Part\ConditionJob;
use Addiks\PHPSQL\Value\Specifier\ColumnSpecifier;
use Addiks\PHPSQL\Index\IndexSchema;
use Addiks\PHPSQL\Iterators\UsesBinaryDataInterface;
use Addiks\PHPSQL\Iterators\AliasedResourceIterator;

class SelectExecutor implements StatementExecutorInterface
{

    public function __construct(
        FilesystemInterface $filesystem,
        SchemaManager $schemaManager,
        TableManager $tableManager,
        ValueResolver $valueResolver
    ) {
        $this->filesystem = $filesystem;
        $this->schemaManager = $schemaManager;
        $this->tableManager = $tableManager;
        $this->valueResolver = $valueResolver;
    }

    protected $filesystem;

    protected $schemaManager;

    protected $tableManager;

    protected $valueResolver;

    public function canExecuteJob(StatementJob $statement)
    {
        return $statement instanceof SelectStatement;
    }

    public function executeJob(StatementJob $statement, array $parameters = array())
    {
        /* @var $statement SelectStatement */

        $defaultSchema = $this->schemaManager->getSchema();

        $executionContext = new ExecutionContext(
            $this->schemaManager,
            $statement,
            $parameters
        );

        ### COLLECT SOURCE TABLES

        if (!is_null($statement->getJoinDefinition())) {
            foreach ($statement->getJoinDefinition()->getTables() as $joinTable) {
                /* @var $joinTable TableJoin */

                /* @var $dataSource ParenthesisPart */
                $dataSource = $joinTable->getDataSource();

                $tableResource = null;
                $alias = $dataSource->getAlias();
                $dataSource = $dataSource->getContain();

                if ($dataSource instanceof TableInterface) {
                    $tableResource = $dataSource;

                    if (is_null($alias)) {
                        $alias = $tableResource->getName();
                    }

                }

                if ($dataSource instanceof SelectStatement) {
                    /* @var $subQueryResult TemporaryResult */
                    $tableResource = $this->executeJob($dataSource, $parameters);
                }

                if ($dataSource instanceof TableSpecifier) {
                    if (!is_null($dataSource->getDatabase())) {
                        if (!$this->schemaManager->schemaExists($dataSource->getDatabase())) {
                            throw new InvalidArgumentException("Database '{$dataSource->getDatabase()}' does not exist!");
                        }

                        $schema = $this->schemaManager->getSchema($dataSource->getDatabase());

                    } else {
                        $schema = $defaultSchema;
                    }

                    if (!$schema->tableExists($dataSource->getTable())) {
                        throw new InvalidArgumentException("Table '{$dataSource}' does not exist!");
                    }

                    if (is_null($alias)) {
                        $alias = $dataSource->getTable();
                    }

                    $tableResource = $this->tableManager->getTable($dataSource->getTable(), $dataSource->getDatabase());
                }

                $executionContext->setTable($tableResource, (string)$alias);
            }
        }

        ### INIT RESULTSET

        $resultColumns = array();
        foreach ($statement->getColumns() as $column) {
            if ($column === '*') {
                foreach ($executionContext->getTables() as $alias => $table) {
                    /* @var $table Table */

                    /* @var $tableSchema TableSchema */
                    $tableSchema = $table->getTableSchema();

                    foreach ($tableSchema->getColumnIterator() as $columnPage) {
                        /* @var $columnPage ColumnSchema */

                        $columnName = $columnPage->getName();

                        if (count($executionContext->getTables())>1) {
                            $columnName = "{$alias}.{$columnName}";
                        }

                        $resultColumns[] = $columnName;
                    }
                }
            } elseif ($column instanceof ValuePart) {
                $resultColumns[] = $column->getAlias();
            }

        }

        $result = new TemporaryResult($resultColumns);

        $executionContext->setCurrentResultSet(new TemporaryResult($resultColumns));

        $executionContext->setCurrentSourceSet(new TemporaryResult());

        ### PRE-FILTER SOURCE COLUMNS (currently not implemented)

        if (!is_null($statement->getCondition())) {
            /* @var $condition ValuePart */
            $condition = $statement->getCondition();

            $tableConditions = $this->findFixedConditions($condition);

            foreach ($executionContext->getTables() as $alias => $table) {
                /* @var $table TableInterface */

                /* @var $tableSchema TableSchema */
                $tableSchema = $table->getTableSchema();

                $tableIterator = $table;

                $indexes = $this->findIndexesForTableConditions($tableConditions, $table, $alias);

                if (!empty($indexes)) {
                    # TODO: actually choose the best index instead of using the first usable index
                    #       (We probably need table-statistics to do that.)
                    $index = $indexes[0];

                    /* @var $indexSchema IndexSchema */
                    $indexSchema = $index->getIndexSchema();

                    $indexecColumns = array();
                    foreach ($indexSchema->getColumns() as $columnId) {
                        $columnName = $tableSchema->getColumn($columnId)->getName();

                        $indexecColumns[$columnId] = $columnName;
                    }

                    $tableIterator = new FilteredResourceIterator(
                        $tableIterator,
                        $condition,
                        $this->valueResolver,
                        $executionContext,
                        $index,
                        $indexecColumns
                    );
                }

                if ($tableIterator !== $table) {
                    $executionContext->setTable($tableIterator, $alias);
                }
            }
        }

        /* @var $joinDefinition Join */
        $joinDefinition = $statement->getJoinDefinition();

        if (!is_null($joinDefinition)) {
            ### BUILD JOIN

            if (count($joinDefinition->getTables()) > 1) {
                $iterator = new JoinIterator(
                    $joinDefinition,
                    $executionContext,
                    $this,
                    $this->valueResolver,
                    $statement,
                    null # TODO: schemaId
                );
            } else {
                /* @var $tableJoin TableJoin */
                $tableJoin = $joinDefinition->getTables()[0];

                $tableSource = $tableJoin->getDataSource();

                $alias = null;
                while ($tableSource instanceof ParenthesisPart) {
                    if (is_null($alias)) {
                        $alias = $tableSource->getAlias();
                    }
                    $tableSource = $tableSource->getContain();
                }

                if ($tableSource instanceof TableSpecifier) {
                    if (is_null($alias)) {
                        $alias = $tableSource->getTable();
                    }
                    $iterator = $executionContext->getTable($alias);

                } elseif ($tableSource instanceof SelectStatement) {
                    $iterator = $this->executeJob($tableSource);

                } else {
                    throw new ErrorException("Unexpected object given as source for join!");
                }

                $iterator = new AliasedResourceIterator($iterator, $alias);
            }

            ### FILTER RESULT

            // WHERE condition
            $condition = $statement->getCondition();
            if (!is_null($condition)) {
                if ($iterator instanceof IteratorAggreagte) {
                    $iterator = $iterator->getIterator();
                }
                $iterator = new FilteredResourceIterator(
                    $iterator,
                    $condition,
                    $this->valueResolver,
                    $executionContext
                );
            }

            // ON/USING conditions from joins
            foreach ($joinDefinition->getTables() as $joinTable) {
                /* @var $joinTable TableJoin */

                $joinCondition = $joinTable->getCondition();
                if (!is_null($joinCondition)) {
                    if ($iterator instanceof IteratorAggreagte) {
                        $iterator = $iterator->getIterator();
                    }
                    $iterator = new FilteredResourceIterator(
                        $iterator,
                        $joinCondition,
                        $this->valueResolver,
                        $executionContext
                    );
                }
            }

            ### SET UP SORTING

            $orderColumns = $statement->getOrderColumns();
            if (count($orderColumns)>0) {
                $innerIterator = $iterator;
                $iterator = new SortedResourceIterator(
                    $iterator,
                    $this->valueResolver
                );

                $iterator->setTemporaryBuildChildIteratorByValue(
                    $orderColumns,
                    $innerIterator,
                    $executionContext
                );
            }

            ### WRITE RESULTSET

            foreach ($iterator as $dataRow) {
                if ($iterator instanceof UsesBinaryDataInterface && $iterator->usesBinaryData()) {
                    $dataRow = $iterator->convertDataRowToStringRow($dataRow);
                }
                $executionContext->getCurrentSourceSet()->addRow($dataRow);
                $executionContext->setCurrentSourceRow($dataRow);
                $resolvedRow = $this->valueResolver->resolveSourceRow($statement->getColumns(), $executionContext);

                $executionContext->getCurrentResultSet()->addRow($resolvedRow);
            }

        } else {
            // no joining (something like "SELECT 5+5 as foo")
            $resolvedRow = $this->valueResolver->resolveSourceRow($statement->getColumns(), $executionContext);
            $executionContext->getCurrentResultSet()->addRow($resolvedRow);
        }

        ### UNLOCK TABLES

        foreach ($executionContext->getTables() as $table) {
            # TODO: unlock tables
        }

        ### APPLY GROUPING

        $groupings = $statement->getGroupings();
        if (count($groupings)>0) {
            foreach ($groupings as $groupingDefinition) {
                /* @var $groupingDefinition GroupingDefinition */

                /* @var $groupingValue ValuePart */
                $groupingValue = $groupingDefinition->getValue();

                $beforeSourceRow = $executionContext->getCurrentSourceRow();

                $groupedRows = array();
                foreach ($executionContext->getCurrentSourceSet() as $row) {
                    $executionContext->setCurrentSourceRow($row);
                    $groupId = $this->valueResolver->resolveValue($groupingValue, $executionContext);

                    if (!isset($groupedRows[$groupId])) {
                        $groupedRows[$groupId] = array();
                    }

                    $groupedRows[$groupId][] = $row;
                }

                $groupedResultSet = new TemporaryResult($resultColumns);

                foreach ($groupedRows as $groupId => $rows) {
                    if ($groupingDefinition->getDirection() === SqlToken::T_ASC()) {
                        $groupingMainRow = reset($rows);
                    } else {
                        $groupingMainRow = end($rows);
                    }

                    $currentGroupResultSet = new TemporaryResult();

                    foreach ($rows as $row) {
                        $currentGroupResultSet->addRow($row);
                    }

                    $executionContext->setCurrentSourceRow($groupingMainRow);
                    $executionContext->setCurrentSourceSet($currentGroupResultSet);

                    $resolvedRow = $this->valueResolver->resolveSourceRow(
                        $statement->getColumns(),
                        $executionContext
                    );

                    $groupedResultSet->addRow($resolvedRow);
                }

                $executionContext->setCurrentSourceRow($beforeSourceRow);
                $executionContext->setCurrentResultSet($groupedResultSet);
            }
        }

        ### APPLY RESULT-FILTER (HAVING)

        /* @var $resultFilter ConditionJob */
        $resultFilter = $statement->getResultFilter();
        if (!is_null($resultFilter)) {
            $filteredResult = new TemporaryResult($resultColumns);
            foreach ($executionContext->getCurrentResultSet() as $row) {
                $executionContext->setCurrentSourceRow($row);
                $passesFilter = (bool)$this->valueResolver->resolveValue($resultFilter, $executionContext);
                if ($passesFilter) {
                    $filteredResult->addRow($row);
                }
            }
            $executionContext->setCurrentResultSet($filteredResult);
        }

        ### APPEND UNIONED SELECT

        $unionSelect = $statement->getUnionSelect();
        if (!is_null($unionSelect)) {
            $unionResult = $this->executeJob($unionSelect, $parameters);

            foreach ($unionResult as $unionRow) {
                $executionContext->getCurrentResultSet()->addRow($unionRow);
            }
        }

        return $executionContext->getCurrentResultSet();
    }

    /**
     * Extracts part(s) of a (complicated) condition that map a column of the given table directly to a fixed value.
     *  (E.g.: "(foo.a > bar.b && baz.c = 1) && (far.d = faz.e || far.f = 3)" results in "baz.c = 1" for table "baz")
     *
     * Only considers the parts that can directly negate the condition result (those liked with AND operators).
     *
     * @param  ValuePart      $condition
     * @return array          (array of ValuePart)
     */
    protected function findFixedConditions(
        Part $condition
    ) {
        $tableConditions = array();

        if ($condition instanceof ValuePart) {
            foreach ($condition->getChainValues() as $chainValue) {
                if ($chainValue instanceof Part) {
                    $tableConditions = array_merge(
                        $tableConditions,
                        $this->findFixedConditions($chainValue)
                    );
                }
            }

        } elseif ($condition instanceof ConditionJob) {
            /* @var $condition ConditionJob */

            if ($condition->getOperator() === Operator::OP_AND()) {
                foreach ([
                    $condition->getFirstParameter(),
                    $condition->getLastParameter()
                ] as $conditionParameter) {
                    if ($conditionParameter instanceof Part) {
                        $tableConditions = array_merge(
                            $tableConditions,
                            $this->findFixedConditions($conditionParameter)
                        );
                    }
                }

            } elseif (in_array($condition->getOperator(), [
                Operator::OP_EQUAL(),
                Operator::OP_EQUAL_NULLSAFE(),
                Operator::OP_NOT_EQUAL(),
                Operator::OP_GREATER(),
                Operator::OP_GREATEREQUAL(),
                Operator::OP_LESSER(),
                Operator::OP_LESSEREQUAL(),
                Operator::OP_BETWEEN(),
                Operator::OP_NOT_BETWEEN(),
            ])) {
                $column = $condition->getFirstParameter();
                $fixedValue = $condition->getLastParameter();

                if ($fixedValue instanceof ColumnSpecifier) {
                    list($column, $fixedValue) = [$fixedValue, $column];
                }

                if ($column instanceof ColumnSpecifier
                 && $this->isFixedValue($fixedValue)) {
                    $tableConditions[] = $condition;
                }
            }
        }

        return $tableConditions;
    }

    private function isFixedValue($fixedValue)
    {
        $isFixedValue = false;

        if (is_scalar($isFixedValue)) {
            $isFixedValue = true;

        } elseif ($fixedValue instanceof ValuePart) {
            $isFixedValue = true;

            foreach ($fixedValue->getChainValues() as $chainValue) {
                $isFixedValue = ($isFixedValue && $this->isFixedValue($chainValue));
            }
        }

        return $isFixedValue;
    }

    /**
     * Finds all usable indexes for given conditions (if any) and table.
     *
     * @param  ConditionJob[] $conditions
     * @param  TableSchema    $tableSchema
     * @param  string         $tableName
     * @param  string         $schemaId
     * @return IndexInterface[]
     */
    protected function findIndexesForTableConditions(
        array $conditions,
        TableInterface $table,
        $tableName
    ) {
        $indexes = array();

        /* @var $tableSchema TableSchema */
        $tableSchema = $table->getTableSchema();

        $conditionColumns = array();

        foreach ($conditions as $condition) {
            /* @var $condition ConditionJob */

            $column = $condition->getFirstParameter();
            $fixedValue = $condition->getLastParameter();

            if ($fixedValue instanceof ColumnSpecifier) {
                list($column, $fixedValue) = [$fixedValue, $column];
            }

            if ($column instanceof ColumnSpecifier) {
                /* @var $column ColumnSpecifier */

                if (is_null($column->getTable()) || $column->getTable() === $tableName) {
                    $columnId = $tableSchema->getColumnIndex($column->getColumn());

                    if (is_int($columnId) && $columnId >= 0) {
                        $conditionColumns[$column->getColumn()] = $columnId;
                    }
                }
            }
        }

        if (!empty($conditionColumns)) {
            foreach ($tableSchema->getIndexIterator() as $indexId => $indexSchema) {
                /* @var $indexSchema IndexSchema */

                $indexColumns = $indexSchema->getColumns();

                if (empty(array_diff($indexColumns, $conditionColumns))) {
                    /* @var $index IndexInterface */
                    $index = $table->getIndex($indexId);

                    $indexes[] = $index;
                }
            }
        }

        return $indexes;
    }

}

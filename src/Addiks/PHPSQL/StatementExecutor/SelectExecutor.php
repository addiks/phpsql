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

use Addiks\PHPSQL\Database;
use Addiks\PHPSQL\SelectResult;
use Addiks\PHPSQL\Executor;
use Addiks\PHPSQL\StatementExecutor\StatementExecutorInterface;
use Addiks\PHPSQL\Entity\Job\StatementJob;
use Addiks\PHPSQL\Entity\Job\Statement\SelectStatement;
use Addiks\PHPSQL\Filesystem\FilesystemInterface;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\ValueResolver;
use Addiks\PHPSQL\Entity\Result\TemporaryResult;
use Addiks\PHPSQL\TableManager;
use Addiks\PHPSQL\Table\TableContainer;
use Addiks\PHPSQL\Entity\Job\Part\ParenthesisPart;
use Addiks\PHPSQL\Value\Specifier\TableSpecifier;
use Addiks\PHPSQL\TableInterface;
use Addiks\PHPSQL\Entity\TableSchema;
use Addiks\PHPSQL\Entity\Page\ColumnPage;
use Addiks\PHPSQL\Entity\Job\Part\ValuePart;
use Addiks\PHPSQL\JoinIterator;
use Addiks\PHPSQL\Entity\ExecutionContext;
use Addiks\PHPSQL\SortedResourceIterator;
use Addiks\PHPSQL\FilteredResourceIterator;
use Addiks\PHPSQL\Entity\Job\Part\Join;
use Addiks\PHPSQL\Entity\Job\Part\Join\TableJoin;
use Addiks\PHPSQL\Entity\Job\Part\GroupingDefinition;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;

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
                            throw new Conflict("Database '{$dataSource->getDatabase()}' does not exist!");
                        }
                        
                        $schema = $this->schemaManager->getSchema($dataSource->getDatabase());
                        
                    } else {
                        $schema = $defaultSchema;
                    }
                    
                    if (!$schema->tableExists($dataSource->getTable())) {
                        throw new Conflict("Table '{$dataSource}' does not exist!");
                    }

                    if (is_null($alias)) {
                        $alias = $dataSource->getTable();
                    }

                    $tableResource = $this->tableManager->getTable($dataSource->getTable());
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
                        /* @var $columnPage ColumnPage */

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
            # TODO: filter tables into temptables, replace them in the ExecutionContext, release locks
        }

        /* @var $joinDefinition Join */
        $joinDefinition = $statement->getJoinDefinition();

        if (!is_null($statement->getJoinDefinition())) {
            ### BUILD JOIN

            $iterator = new JoinIterator(
                $joinDefinition,
                $executionContext,
                $this,
                $this->valueResolver,
                $statement,
                null # TODO: schemaId
            );

            ### SET UP SORTING

            $orderColumns = $statement->getOrderColumns();
            if (count($orderColumns)>0) {
                $joinIterator = $iterator;
                $iterator = new SortedResourceIterator(
                    $iterator,
                    $this->valueResolver
                );

                $iterator->setTemporaryBuildChildIteratorByValue(
                    $orderColumns,
                    $joinIterator,
                    $executionContext
                );
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

            ### WRITE RESULTSET

            foreach ($iterator as $dataRow) {
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
}

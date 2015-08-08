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

        if (!is_null($statement->getCondition())) {
            # TODO: filter tables into temptables
        }

        $result = new TemporaryResult($resultColumns);

        if (!is_null($statement->getJoinDefinition())) {
            $joinIterator = new JoinIterator(
                $executionContext,
                $this,
                $this->valueResolver,
                $statement,
                null # TODO: schemaId
            );

            foreach ($joinIterator as $dataRow) {
                $executionContext->setCurrentSourceRow($dataRow);
                $resolvedRow = $this->valueResolver->resolveSourceRow($statement->getColumns(), $executionContext);

                $result->addRow($resolvedRow);
            }

        } else {
            $resolvedRow = $this->valueResolver->resolveSourceRow($statement->getColumns(), $executionContext);

            $result->addRow($resolvedRow);
        }

        return $result;
        
        $result = new SelectResult(
            $this->filesystem,
            $this->schemaManager,
            $this->valueResolver,
            $statement,
            $parameters
        );

        return $result;
    }
}

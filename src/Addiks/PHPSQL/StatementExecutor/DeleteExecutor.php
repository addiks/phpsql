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
use Addiks\PHPSQL\Entity\Result\TemporaryResult;
use Addiks\PHPSQL\Database;
use Addiks\PHPSQL\Entity\Job\StatementJob;
use Addiks\PHPSQL\SqlParser\DeleteSqlParser;
use Addiks\PHPSQL\ValueResolver;
use Addiks\PHPSQL\TableManager;
use Addiks\PHPSQL\BinaryConverterTrait;

class DeleteExecutor implements StatementExecutorInterface
{
    
    use BinaryConverterTrait;
    
    public function __construct(
        ValueResolver $valueResolver,
        TableManager $tableManager
    ) {
        $this->valueResolver = $valueResolver;
        $this->tableManager = $tableManager;
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
    
    public function canExecuteJob(StatementJob $statement)
    {
        return $statement instanceof DeleteSqlParser;
    }

    public function executeJob(StatementJob $statement, array $parameters = array())
    {
        /* @var $statement DeleteSqlParser */
        
        $result = new TemporaryResult();
        
        $this->valueResolver->setResultSet($result);
        $this->valueResolver->setStatement($statement);
        $this->valueResolver->setStatementParameters($parameters);
        $this->valueResolver->resetParameterCurrentIndex();
        
        /* @var $conditionValue Value */
        $conditionValue = $statement->getCondition();
        
        $rowCount = 0;
        $rowSkip  = 0;
        
        $limitOffset = $statement->getLimitOffset();
        $limitCount  = $statement->getLimitRowCount();
        
        foreach ($statement->getDeleteTables() as $tableSpecifier) {
            /* @var $tableSpecifier TableSpecifier */
            
            /* @var $tableResource Table */
            $tableResource = $this->tableManager->getTable(
                $tableSpecifier->getTable(),
                $tableSpecifier->getDatabase()
            );

            $sortedIterator = new SortedResourceIterator();
            
            /* @var $tableSchema TableSchema */
            $tableSchema = $tableResource->getTableSchema();
            
            $sortedIterator->setResourceTable($tableResource);
            
            if (!is_null($statement->getOrderColumn())) {
                $orderColumns = $statement->getOrderColumn();
                
                $sortedIterator->setTemporaryBuildChildIteratorByValue($orderColumns, $tableResource);
                
            } else {
                $primaryKeyColumns = $tableSchema->getPrimaryKeyColumns();
                
                $primaryIndexId = $tableSchema->getIndexIdByColumns(array_keys($primaryKeyColumns));
                
                if (!is_null($primaryIndexId)) {
                    /* @var $index Index */
                    $index = $this->tableManager->getIndex(
                        $primaryIndexId,
                        $tableSpecifier->getTable(),
                        $tableSpecifier->getDatabase()
                    );
                    
                    // TODO: try to extract begin/end values from conditions
                    $beginValue = null;
                    $endValue = null;
                    
                    $iterator = $index->getIterator($beginValue, $endValue);
                    
                    $sortedIterator->setChildIterator($iterator);
                    
                } else {
                    /* @var $sortIndex Quicksort */
                    $sortIndex = $sortedIterator->getSortIndexByColumns($primaryKeyColumns);
                    
                    foreach ($tableResource as $rowId => $row) {
                        $sortIndex->addRow($rowId, $row);
                    }
                    $sortIndex->sort();
                        
                    $sortedIterator->setChildIterator($sortIndex);
                }
            }
            
            foreach ($sortedIterator->getIterator() as $rowId => $row) {
                $this->valueResolver->setSourceRow($row);
                $this->valueResolver->resetParameterCurrentIndex();
                
                $isConditionMatch = is_null($conditionValue) || $this->valueResolver->resolveValue($conditionValue);
                
                if ($isConditionMatch) {
                    if (!is_null($limitOffset) && $rowSkip < $limitOffset) {
                        $rowSkip++;
                        continue;
                    }
                    
                    if (!is_null($limitCount) && $rowCount > $limitCount) {
                        continue;
                    }
                    $rowCount++;
                    
                    foreach ($tableResource->getTableSchema()->getIndexIterator() as $indexId => $indexPage) {
                        /* @var $indexPage Index */
                        
                        /* @var $indexResource Index */
                        $indexResource = $this->tableManager->getIndex(
                            $indexId,
                            $tableSpecifier->getTable(),
                            $tableSpecifier->getDatabase()
                        );
                        
                        $indexResource->remove($tableResource->getRowData($rowId), $this->decstr($rowId));
                    }
                    
                    $tableResource->removeRow($rowId);
                }
            }
        }
        
        return $result;
    }
}

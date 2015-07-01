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

namespace Addiks\PHPSQL\Service\Executor;

use Addiks\PHPSQL\Service\Executor;

use Addiks\PHPSQL\Entity\Result\Temporary;

use Addiks\PHPSQL\Resource\Database;

class DeleteExecutor extends Executor
{
    
    use BinaryConverterTrait;
    
    protected function executeConcreteJob($statement, array $parameters = array())
    {
        /* @var $statement Delete */
        
        /* @var $result Temporary */
        $this->factorize($result);
        
        /* @var $databaseResource Database */
        $this->factorize($databaseResource);
        
        /* @var $valueResolver ValueResolver */
        $this->factorize($valueResolver);
        
        $valueResolver->setResultSet($result);
        $valueResolver->setStatement($statement);
        $valueResolver->setStatementParameters($parameters);
        $valueResolver->resetParameterCurrentIndex();
        
        /* @var $conditionValue Value */
        $conditionValue = $statement->getCondition();
        
        $rowCount = 0;
        $rowSkip  = 0;
        
        $limitOffset = $statement->getLimitOffset();
        $limitCount  = $statement->getLimitRowCount();
        
        foreach ($statement->getDeleteTables() as $tableSpecifier) {
            /* @var $tableSpecifier TableSpecifier */
            
            /* @var $tableResource Table */
            $this->factorize($tableResource, [$tableSpecifier->getTable(), $tableSpecifier->getDatabase()]);
            
            /* @var $sortedIterator SortedResourceIterator */
            $this->factorize($sortedIterator);
            
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
                    $this->factorize($index, [$primaryIndexId, $tableSpecifier->getTable(), $tableSpecifier->getDatabase()]);
                    
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
                $valueResolver->setSourceRow($row);
                $valueResolver->resetParameterCurrentIndex();
                
                $isConditionMatch = is_null($conditionValue) || $valueResolver->resolveValue($conditionValue);
                
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
                        $this->factorize($indexResource, [$indexId, $tableSpecifier->getTable(), $tableSpecifier->getDatabase()]);
                        
                        $indexResource->remove($tableResource->getRowData($rowId), $this->decstr($rowId));
                    }
                    
                    $tableResource->removeRow($rowId);
                }
            }
        }
        
        return $result;
    }
}

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

class UpdateExecutor extends Executor
{
    
    protected function executeConcreteJob($statement, array $parameters = array())
    {
        /* @var $statement Update */
        
        /* @var $databaseResource Database */
        $this->factorize($databaseResource);
        
        /* @var $result Temporary */
        $this->factorize($result);
        
        // TODO: multiple tables or not?
        
        /* @var $tableSpecifier TableSpecifier */
        $tableSpecifier = $statement->getTables()[0];
        
        if (!$databaseResource->getSchema()->tableExists((string)$tableSpecifier)) {
            throw new Conflict("Table '{$tableSpecifier}' does not exist!");
        }
        
        /* @var $tableResource Table */
        $this->factorize($tableResource, [$tableSpecifier]);
        
        /* @var $tableSchema TableSchema */
        $tableSchema = $tableResource->getTableSchema();
        
        $indicies = array();
        foreach ($tableSchema->getIndexIterator() as $indexId => $indexPage) {
            /* @var $indexPage Index */
            
            /* @var $index Index */
            $this->factorize($index, [$indexPage->getName(), $tableSpecifier->getTable(), $tableSpecifier->getDatabase()]);
            
            $indicies[] = $index;
        }
        
        /* @var $valueResolver ValueResolver */
        $this->factorize($valueResolver);
        
        $valueResolver->setStatement($statement);
        $valueResolver->setResultSet($result);
        $valueResolver->setStatementParameters($parameters);
        
        /* @var $condition Value */
        $condition = $statement->getCondition();
        
        foreach ($tableResource->getIterator() as $rowId => $row) {
            $row = $tableResource->convertDataRowToStringRow($row);
            
            $valueResolver->setSourceRow($row);
            
            $conditionResult = $valueResolver->resolveValue($condition);
            
            if ($conditionResult) {
                $newRow = array();
                foreach ($statement->getDataChanges() as $dataChange) {
                    /* @var $dataChange DataChange */
                    
                    $columnName = (string)$dataChange->getColumn();
                    
                    $newValue = $dataChange->getValue();
                    $newValue = $valueResolver->resolveValue($newValue);
                    
                    $newRow[$columnName] = $newValue;
                }
                
                $newRow = $tableResource->convertStringRowToDataRow($newRow);
                
                foreach ($indicies as $index) {
                    /* @var $index Index */
                    
                    $index->update($rowId, $row, $newRow);
                }
                
                $tableResource->setRowData($rowId, $newRow);
            }
        }
        
        return $result;
    }
}

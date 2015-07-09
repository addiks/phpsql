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

namespace Addiks\PHPSQL\Executor;

use Addiks\PHPSQL\Executor;

use Addiks\PHPSQL\Entity\Result\Temporary;

use Addiks\PHPSQL\Database;

class UpdateExecutor extends Executor
{
    
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
    
    protected function executeConcreteJob($statement, array $parameters = array())
    {
        /* @var $statement Update */
        
        $result = new TemporaryResult();
        // TODO: multiple tables or not?
        
        /* @var $tableSpecifier TableSpecifier */
        $tableSpecifier = $statement->getTables()[0];
        
        /* @var $tableResource Table */
        $tableResource = $this->tableManager->getTable($tableSpecifier);

        /* @var $tableSchema TableSchema */
        $tableSchema = $tableResource->getTableSchema();
        
        $indicies = array();
        foreach ($tableSchema->getIndexIterator() as $indexId => $indexPage) {
            /* @var $indexPage Index */
            
            /* @var $index Index */
            $index = $this->tableManager->getIndex(
                $indexPage->getName(),
                $tableSpecifier->getTable(),
                $tableSpecifier->getDatabase()
            );

            $indicies[] = $index;
        }
        
        $this->valueResolver->setStatement($statement);
        $this->valueResolver->setResultSet($result);
        $this->valueResolver->setStatementParameters($parameters);
        
        /* @var $condition Value */
        $condition = $statement->getCondition();
        
        foreach ($tableResource->getIterator() as $rowId => $row) {
            $row = $tableResource->convertDataRowToStringRow($row);
            
            $this->valueResolver->setSourceRow($row);
            
            $conditionResult = $this->valueResolver->resolveValue($condition);
            
            if ($conditionResult) {
                $newRow = array();
                foreach ($statement->getDataChanges() as $dataChange) {
                    /* @var $dataChange DataChange */
                    
                    $columnName = (string)$dataChange->getColumn();
                    
                    $newValue = $dataChange->getValue();
                    $newValue = $this->valueResolver->resolveValue($newValue);
                    
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

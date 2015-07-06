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

use Addiks\PHPSQL\Index;
use Addiks\PHPSQL\ValueResolver;
use Addiks\PHPSQL\Table;
use Addiks\PHPSQL\BinaryConverterTrait;
use Addiks\PHPSQL\Executor;
use Addiks\PHPSQL\Entity\Result\Temporary;
use Addiks\PHPSQL\Database;
use ErrorException;
use Exception;

class InsertExecutor extends Executor
{
    
    use BinaryConverterTrait;
    
    protected function executeConcreteJob($statement, array $parameters = array())
    {
        /* @var $statement Insert */
        
        /* @var $result Temporary */
        $this->factorize($result);
        
        /* @var $databaseResource Database */
        $this->factorize($databaseResource);
        
        /* @var $databaseSchema Schema */
        $databaseSchema = $databaseResource->getSchema();
        
        $tableName = (string)$statement->getTable();
        
        /* @var $table Table */
        $this->factorize($table, [$tableName]);
        
        /* @var $tableSchema TableSchema */
        $tableSchema = $table->getTableSchema();
        
        /* @var $valueResolver ValueResolver */
        $this->factorize($valueResolver);
        
        $valueResolver->setStatement($statement);
        $valueResolver->setStatementParameters($parameters);
        
        ### BUILD COLUMN MAP
        
        $columnNameToIdMap = array();
        
        foreach ($tableSchema->getColumnIterator() as $columnId => $columnPage) {
            /* @var $columnPage Column */
            
            $columnName = $columnPage->getName();
            
            $columnNameToIdMap[$columnName] = $columnId;
        }
        
        ### GET INDICES
        
        $indices = array();
        
        foreach ($tableSchema->getIndexIterator() as $indexId => $indexPage) {
            /* @var $indexPage Index */
            
            /* @var $index Index */
            $this->factorize($index, [$indexId, $tableName]);
            
            $indices[$indexId] = $index;
        }
        
        ### BUILD ROW DATA
        
        $rowDatas = array();
        
        switch(true){
            
            case $statement->getDataSource() instanceof Select:
                
                /* @var $selectExecutor Select */
                $this->factorize($selectExecutor);
                
                $subResult = $selectExecutor->executeJob($statement->getDataSource(), $parameters);
                
                foreach ($subResult as $subResultRow) {
                    $rowData = array();
                    foreach ($subResultRow as $columnName => $value) {
                        $columnId = $columnNameToIdMap[$columnName];
                        $rowData[$columnId] = $value;
                    }
                    $rowDatas[] = $rowData;
                }
                break;
                
            case is_array($statement->getDataSource()):
                
                foreach ($statement->getDataSource() as $row) {
                    $sourceRow = array();
                    foreach ($statement->getColumns() as $sourceColumnId => $sourceColumn) {
                        /* @var $sourceColumn Column */
                        
                        $sourceColumnName = (string)$sourceColumn;
                        
                        $sourceRow[$sourceColumnName] = $row[$sourceColumnId];
                    }
                    
                    $rowData = array();
                    
                    foreach ($statement->getColumns() as $sourceColumnId => $sourceColumn) {
                        /* @var $sourceColumn Column */
                        
                        $columnName = (string)$sourceColumn;
                        
                        if (!isset($columnNameToIdMap[$columnName])) {
                            throw new ErrorException("Unknown column '{$columnName}' in statement!");
                        }
                        
                        $columnId = $columnNameToIdMap[$columnName];
                        
                        if (isset($sourceRow[$columnName])) {
                            $value = $sourceRow[$columnName];
                            $value = $valueResolver->resolveValue($value);
                        } else {
                            $value = null;
                        }
                        
                        /* @var $columnPage Column */
                        $columnPage = $tableSchema->getColumn($columnId);
                        
                        if (is_null($value) && $columnPage->isAutoIncrement()) {
                            $value = $table->getAutoIncrementId();
                            $table->incrementAutoIncrementId();
                        }
                        
                        if ($columnPage->isNotNull() && is_null($value)) {
                            $columnName = $tableSchema->getColumn($columnId)->getName();
                            throw new ErrorException("Column '{$columnName}' cannot be NULL!");
                        }
                        
                        $rowData[$columnId] = $value;
                    }
                    
                    $primaryKey = array();
                    
                    // fill up missing columns
                    foreach ($columnNameToIdMap as $columnName => $columnId) {
                        /* @var $columnPage Column */
                        $columnPage = $tableSchema->getColumn($columnId);
                            
                        if (!isset($rowData[$columnId])) {
                            if ($columnPage->isNotNull()) {
                                if ($columnPage->isAutoIncrement()) {
                                    $rowData[$columnId] = $table->getAutoIncrementId();
                                    $table->incrementAutoIncrementId();
                                }
                            }
                        }
                        
                        if ($columnPage->isPrimaryKey()) {
                            $primaryKey[$columnName] = $rowData[$columnId];
                        }
                    }
                    
                    $result->setLastInsertId($primaryKey);
                    
                    $rowData = $table->convertStringRowToDataRow($rowData);
                    
                    foreach ($indices as $indexId => $index) {
                        /* @var $index Index */
                        
                        if (!$index->getIndexPage()->isUnique()) {
                            continue;
                        }
                        if (count($index->search($rowData))>0) {
                            $rowDataString = implode(", ", $rowData);
                            throw new ErrorException("Cannot insert because row '{$rowDataString}' collides with unique key '{$index->getIndexPage()->getName()}'!");
                        }
                    }
                    $rowDatas[] = $rowData;
                }
                break;
            
        }
        
        ### INSERT DATA
            
        $insertedRowIds = array();
        
        $success = false;
        
        try {
            foreach ($rowDatas as $rowData) {
                // check unique keys
                foreach ($indices as $indexId => $index) {
                    if (!$index->getIndexPage()->isUnique()) {
                        continue;
                    }
                    if (count($index->search($rowData))>0) {
                        throw new ErrorException("Cannot insert because of unique key '{$index->getIndexPage()->getName()}'!");
                    }
                }
                
                $rowId = $table->addRowData($rowData);
                $insertedRowIds[] = $rowId;
                
                // insert into indicies
                foreach ($indices as $indexId => $index) {
                    $index->insert($rowData, $this->decstr($rowId));
                }
            }
            
            $success = true;
            
        } catch (Exception $exception) {
            ### ROLLBACK
            
            foreach ($insertedRowIds as $rowId) {
                $table->removeRow($rowId);
                
                // remove from indicies
                foreach ($indices as $indexId => $index) {
                    $index->remove($row, $this->decstr($rowId));
                }
            }
            
            throw new ErrorException("Exception in INSERT statement, rollback executed.", null, $exception);
        }
        
        ### RESULT
        
        $result->setIsSuccess((bool)$success);
        
        return $result;
    }
}

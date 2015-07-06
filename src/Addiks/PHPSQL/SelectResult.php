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

use Addiks\PHPSQL\ValueResolver;
use Addiks\PHPSQL\Entity\Job\Statement\SelectStatement;
use Addiks\PHPSQL\Entity\Result\ResultInterface;
use Addiks\PHPSQL\BinaryConverterTrait;
use Addiks\PHPSQL\Entity\Index\IndexInterface;
use Addiks\PHPSQL\CustomIterator;
use ErrorException;

/**
 * This is an dynamic result-set specific for SELECT statements.
 *
 */
class SelectResult implements ResultInterface, \IteratorAggregate
{

    use BinaryConverterTrait;
    
    public function __construct(
        SelectStatement $statement,
        array $statementParameters = array(),
        array $resultSpecificValues = array(),
        $schemaId = null
    ) {

        /* @var $databaseResource Database */
        $this->factorize($databaseResource);
        
        $this->statementParameters = $statementParameters;
        
        $defaultSchema = $databaseResource->getSchema();
        
        if (!is_null($statement->getJoinDefinition())) {
            foreach ($statement->getJoinDefinition()->getTables() as $joinTable) {
                /* @var $joinTable Table */
                
                /* @var $dataSource Table */
                $dataSource = $joinTable->getDataSource();
                    
                if ($dataSource instanceof Parenthesis) {
                    $dataSource = $dataSource->getContain();
                }
                
                if (!$dataSource instanceof Table) {
                    continue;
                }
                
                if (!is_null($dataSource->getDatabase())) {
                    if (!$databaseResource->schemaExists($dataSource->getDatabase())) {
                        throw new Conflict("Database '{$dataSource->getDatabase()}' does not exist!");
                    }
                    
                    $schema = $databaseResource->getSchema($dataSource->getDatabase());
                    
                } else {
                    $schema = $defaultSchema;
                }
                
                if (!$schema->tableExists($dataSource->getTable())) {
                    throw new Conflict("Table '{$dataSource}' does not exist!");
                }
            }
        }
        
        $this->setStatement($statement);
        $this->setResultSpecificValues($resultSpecificValues);
        $this->schemaId = $schemaId;
        
        /* @var $storages \Addiks\PHPSQL\Storages */
        $this->factorize($storages);
        
        $this->storages = $storages;
        
        /* @var $valueResolver ValueResolver */
        $this->factorize($valueResolver);
        
        $valueResolver->setStatement($statement);
        $valueResolver->setResultSet($this);
        
        $this->valueResolver = $valueResolver;
        
    }
    
    private $storages;
    
    private $schemaId;

    public function getSchemaId()
    {
        return $this->schemaId;
    }

    private $statementParameters;
    
    public function getStatementParameters()
    {
        return $this->statementParameters;
    }
    
    private $isSuccess = true;

    public function setIsSuccess($bool)
    {
        $this->isSuccess = (bool)$bool;
    }

    public function getIsSuccess()
    {
        return $this->isSuccess;
    }
    
    private $lastInsertId = array();
    
    /**
     * @return array
     */
    public function getLastInsertId()
    {
        return $this->lastInsertId;
    }
    
    public function setLastInsertId(array $row)
    {
        $this->lastInsertId;
    }
    
    private $headerCache;

    public function getHeaders()
    {

        if (is_null($this->headerCache)) {
            $iterator = $this->getIterator();
            
            $iterator->rewind();
            
            if (!$iterator->valid()) {
                return array();
            }
            
            $row = $iterator->current();
                
            $this->headerCache = array_keys($row);
        }
        
        return $this->headerCache;
    }

    /**
     * @return bool
     */
    public function getHasResultRows()
    {

        $iterator = $this->getIterator();

        $iterator->rewind();
        
        return $iterator->valid();
    }

    private $statement;

    public function getStatement()
    {
        return $this->statement;
    }

    public function setStatement(SelectStatement $statement)
    {
        $this->statement = $statement;
    }

    private $resultSpecificValues = array();

    public function setResultSpecificValues(array $values)
    {
        $this->resultSpecificValues[] = $values;
    }

    public function getResultSpecificValues()
    {
        return $this->resultSpecificValues;
    }

    public function setResultSpecificValue($alias, $value)
    {
        $this->resultSpecificValues[$alias] = $value;
    }

    public function getResultSpecificValue($alias)
    {
        return $this->resultSpecificValues[$alias];
    }

    ### ITERATOR

    /**
     * Because we cannot implement \Iterator and \IteratorAggregate:
     * @see https://bugs.php.net/bug.php?id=48667
     * i have to use this silly trick:
     *
     * @see IteratorAggregate::getIterator()
     */
    public function getIterator()
    {
        
        $selectResult = $this;
        
        return new CustomIterator(null, [
            'rewind' => function () use ($selectResult) {
                $selectResult->rewindIterator();
            },
            'valid' => function () use ($selectResult) {
                return $selectResult->validIterator(true);
            },
            'current' => function () use ($selectResult) {
                return $selectResult->currentIterator();
            },
            'key' => function () use ($selectResult) {
                return $selectResult->keyIterator();
            },
            'next' => function () use ($selectResult) {
                $selectResult->nextIterator();
            },
        ]);
    }
    
    private $resultRow;
    
    private $joinIterator;

    private $valueResolver;

    private $rowOutputCount = 0;
    
    /**
     *
     * @var HashTable
     */
    private $groupByHashTable;
    
    public function getGroupByHashTable()
    {
        return $this->groupByHashTable;
    }
    
    public function getCurrentGroupedRowIds()
    {
        
        if (is_null($this->getGroupByHashTable())) {
            return array();
        }
        
        /* @var $valueResolver ValueResolver */
        $this->factorize($valueResolver);
            
        $valueResolver->setSourceRow($this->currentIteratorUnresolved());
            
        $groupingValue = "";
        foreach ($this->getStatement()->getGroupings() as $groupingDataset) {
            /* @var $value Value */
            $value = $groupingDataset['value'];
        
            $value = $valueResolver->resolveValue($value);
        
            $value = str_pad($value, 64, "\0", STR_PAD_LEFT);
            $value = substr($value, 0, 64);
        
            $groupingValue .= $value;
        }
        
        /* @var $groupingHashTable HashTable */
        $groupingHashTable = $this->getGroupByHashTable();
        $groupingHashTable->setCacheBackend($this->getUsableCacheBackend());
        
        $rowIds = $groupingHashTable->search($groupingValue);
        
        foreach ($rowIds as &$rowId) {
            $rowId = $this->strdec($rowId);
        }
        
        return $rowIds;
    }
    
    public function rewindIterator()
    {
        
        /* @var $valueResolver ValueResolver */
        $this->factorize($valueResolver);
        
        /* @var $statement SelectStatement */
        $statement = $this->getStatement();
        
        // select without tables like "SELECT RAND()"
        if (is_null($statement->getJoinDefinition())) {
            $this->resultRow = array();
            
            foreach ($statement->getColumns() as $alias => $value) {
                $this->resultRow[$alias] = (string)$valueResolver->resolveValue($value);
            }
            
            return;
        }
        
        /* @var $databaseResource Database */
        $this->factorize($databaseResource);
        
        foreach ($statement->getJoinDefinition()->getTables() as $joinTable) {
            /* @var $joinTable Table */
            
            $dataSource = $joinTable->getDataSource();
                
            if ($dataSource instanceof Parenthesis) {
                $dataSource = $dataSource->getContain();
            }
            
            if (!$dataSource instanceof Table) {
                continue;
            }
            
            if (!is_null($dataSource->getDatabase())) {
                $database = $dataSource->getDatabase();
            } else {
                $database = null;
            }
            
            $tableName = $dataSource->getTable();
            
            if (!$databaseResource->getSchema($database)->tableExists($tableName)) {
                throw new InvalidArgument("Table '{$dataSource}' does not exist!");
            }
        }
        
        /* @var $joinIterator JoinIterator */
        $this->factorize($joinIterator, [$statement]);

        $this->joinIterator = $joinIterator;
        
        $joinIterator->rewind();
        while ($this->validIterator() && !$this->getDoConditionsMatch()) {
            $this->nextWithoutConditionCheck();
        }
        
        ### GROUP BY
        
        if (count($statement->getGroupings())>0) {
            $keyLength = count($statement->getGroupings())*64;
            $uniqid = uniqid();
            
            /* @var $groupingHashTable HashTable */
            $this->factorize($groupingHashTable, [$this->getStorage("Temporary/GroupByHashTables/{$uniqid}"), $keyLength]);
            
            $groupingHashTable->getStorage()->setIsTemporary(true);
            
            while ($this->validIterator()) {
                $sourceRow = $this->currentIterator();
                
                $valueResolver->setSourceRow($sourceRow);
                
                $groupingValue = "";
                foreach ($statement->getGroupings() as $groupingDataset) {
                    /* @var $value Value */
                    $value = $groupingDataset['value'];
                    
                    $value = $valueResolver->resolveValue($value);
                    
                    $value = str_pad($value, 64, "\0", STR_PAD_LEFT);
                    $value = substr($value, 0, 64);
                    
                    $groupingValue .= $value;
                }
                
                $rowId = $this->keyIterator();
                
                $groupingHashTable->insert($groupingValue, $rowId);
                
                $this->nextIterator();
            }
            
            $this->groupByHashTable = $groupingHashTable;
                
            $joinIterator->rewind();
            while ($this->validIterator() && (!$this->getDoConditionsMatch())) {
                $this->nextWithoutConditionCheck();
            }
        }
        
        for ($index=0; $index<$statement->getLimitOffset(); $index++) {
            $this->nextIterator();
        }
        
        $this->rowOutputCount = 0;
    }

    public function validIterator($considerLimits = false)
    {
        
        if (is_null($this->joinIterator)) {
            return !is_null($this->resultRow);
        }
        
        /* @var $statement SelectStatement */
        $statement = $this->getStatement();
        
        if (!is_null($statement->getLimitRowCount()) && $considerLimits) {
            if ($statement->getLimitRowCount() <= $this->rowOutputCount) {
                return false;
            }
        }
        
        return $this->joinIterator->valid();
    }

    public function currentIteratorUnresolved()
    {
        
        if (is_null($this->joinIterator)) {
            return is_array($this->resultRow) ?$this->resultRow :null;
        }
        
        $sourceRows = $this->joinIterator->current();
        
        $values = $this->getResultSpecificValues();
        
        $sourceRow = array();
        
        if (is_null($sourceRows)) {
            return $sourceRow;
        }
        
        foreach ($sourceRows as $tableAlias => $row) {
            if (!is_array($row)) {
                throw new ErrorException("Join-Iterator returned non-array as row!");
            }
            
            foreach ($row as $column => $value) {
                $sourceRow[$column] = $value;
                $sourceRow["{$tableAlias}.{$column}"] = $value;
            }
        }
        
        return $sourceRow;
    }
    
    public function currentIterator()
    {

        $sourceRow = $this->currentIteratorUnresolved();
        
        if (is_null($sourceRow)) {
            return null;
        }
        
        $row = $this->valueResolver->resolveSourceRow($sourceRow);

        return $row;
    }

    public function keyIterator()
    {
        
        if (is_null($this->joinIterator)) {
            return 0;
        }
        
        return $this->joinIterator->key();
    }
    
    protected function getDoConditionsMatch()
    {
    
        /* @var $statement SelectStatement */
        $statement = $this->getStatement();
    
        $conditionJob = $statement->getCondition();
    
        if (is_null($conditionJob)) {
            return true;
        }
    
        /* @var $valueResolver ValueResolver */
        $this->factorize($valueResolver);
        
        $valueResolver->setSourceRow($this->currentIteratorUnresolved());
        $valueResolver->setStatementParameters($this->getStatementParameters());
        $valueResolver->resetParameterCurrentIndex();
        
        $result = $valueResolver->resolveValue($conditionJob);
    
        return $result;
    }
    
    protected function getIsGroupedRowAlreadyOutputted()
    {
        
        if (!$this->getGroupByHashTable() instanceof HashTable) {
            return false;
        }
        
        /* @var $groupingHashTable HashTable */
        $groupingHashTable = $this->getGroupByHashTable();
            
        /* @var $valueResolver ValueResolver */
        $this->factorize($valueResolver);
            
        $valueResolver->setSourceRow($this->currentIteratorUnresolved());
            
        $groupingValue = "";
        foreach ($this->getStatement()->getGroupings() as $groupingDataset) {
            /* @var $value Value */
            $value = $groupingDataset['value'];
    
            $value = $valueResolver->resolveValue($value);
    
            $value = str_pad($value, 64, "\0", STR_PAD_LEFT);
            $value = substr($value, 0, 64);
    
            $groupingValue .= $value;
        }
        
        $rowIds = $groupingHashTable->search($groupingValue);
        
        $currentRowId = $this->keyIterator();
            
        foreach ($rowIds as $rowId) {
            $rowId = $this->strdec($rowId);
                
            if ($currentRowId > $rowId) {
                return true;
            }
        }
        
        return false;
    }
    
    protected function nextWithoutConditionCheck()
    {
        
        if (is_null($this->joinIterator)) {
            $this->resultRow = null;
            return;
        }
        
        $this->joinIterator->next();
    }
    
    public function nextIterator()
    {
        
        do {
            $this->nextWithoutConditionCheck();
        } while ($this->validIterator() && (!$this->getDoConditionsMatch() || $this->getIsGroupedRowAlreadyOutputted()));
        
        $this->rowOutputCount++;
    }
        

    public function seek($rowId)
    {
        
        if (is_null($this->joinIterator)) {
            $this->resultRow = null;
            return;
        }
        
        $this->joinIterator->seek($rowId);
    }
    
    public function count()
    {
        
        if ($this->joinIterator instanceof JoinIterator) {
            return $this->joinIterator->count();
        }
        
        return 0;
    }
    
    public function getRow($rowId)
    {
        
        $beforeRowId = $this->keyIterator();
        
        $this->seek($rowId);
        
        $row = $this->currentIterator();
        
        $this->seek($beforeRowId);
        
        return $row;
    }
    
    public function getRowUnresolved($rowId)
    {
        
        $beforeRowId = $this->keyIterator();
    
        $this->seek($rowId);
    
        $row = $this->currentIteratorUnresolved();
    
        $this->seek($beforeRowId);
        
        return $row;
    }
    
    /**
     * Alias of fetchArray
     * @return array
     */
    public function fetch()
    {
        return $this->fetchArray();
    }
    
    /**
     * @return array
     */
    public function fetchArray()
    {
        
        if (is_null($this->joinIterator)) {
            $this->rewindIterator();
        }
        
        $row = $this->currentIterator();
        $this->nextIterator();
        
        $number = 0;
        foreach ($row as $value) {
            $row[$number] = $value;
            $number++;
        }
        
        return $row;
    }
    
    /**
     * @return array
     */
    public function fetchAssoc()
    {
        
        if (is_null($this->joinIterator)) {
            $this->rewindIterator();
        }
        
        if ($this->validIterator()) {
            $row = $this->currentIterator();
            $this->nextIterator();
            
            return $row;
        }
    }
    
    /**
     * @return array
     */
    public function fetchRow()
    {
        
        if (is_null($this->joinIterator)) {
            $this->rewindIterator();
        }
        
        $row = $this->currentIterator();
        $this->nextIterator();
        
        $returnRow = array();
        $number = 0;
        foreach ($row as $value) {
            $returnRow[$number] = $value;
            $number++;
        }
        
        return $returnRow;
    }
    
    private $columnMetaData = array();
    
    public function setColumnMetaData($columnName, array $data)
    {
        $this->columnMetaData[$columnName] = $data;
    }
    
    public function getColumnMetaData($columnName)
    {
        return $this->columnMetaData[$columnName];
    }
}

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

use Addiks\PHPSQL\Value\Specifier\TableSpecifier;
use Addiks\PHPSQL\SortedResourceIterator;
use Addiks\PHPSQL\Entity\Job\Statement\SelectStatement;
use Addiks\PHPSQL\Entity\Result\ResultInterface;
use ErrorException;
use SeekableIterator;
use Countable;
use IteratorAggregate;
use Iterator;
use Addiks\PHPSQL\Database;
use Addiks\PHPSQL\StatementExecutor\SelectExecutor;
use Addiks\PHPSQL\ValueResolver;
use Addiks\PHPSQL\Entity\ExecutionContext;
use Addiks\PHPSQL\Entity\Job\Part\Join;
use Addiks\PHPSQL\Entity\Job\Part\Join\TableJoin;
use Addiks\PHPSQL\Entity\Job\Part\ParenthesisPart;
use Addiks\PHPSQL\FilteredResourceIterator;
use Addiks\PHPSQL\UsesBinaryDataInterface;

/**
 * The purpose of this component is to cross-join in any needed way
 * between multiple data-sources (tables, resultsets, indexes, ...).
 */
class JoinIterator implements SeekableIterator, Countable, ResultInterface
{

    public function __construct(
        Join $joinDefinition,
        ExecutionContext $executionContext,
        SelectExecutor $selectExecutor,
        ValueResolver $valueResolver
    ) {
        $this->joinDefinition = $joinDefinition;
        $this->executionContext = $executionContext;
        $this->selectExecutor = $selectExecutor;
        $this->valueResolver = $valueResolver;
    }

    protected $valueResolver;

    protected $executionContext;

    public function getExecutionContext()
    {
        return $this->executionContext;
    }

    protected $selectExecutor;

    public function getSelectExecutor()
    {
        return $this->selectExecutor;
    }
    
    public function getIsSuccess()
    {
        return true;
    }
    
    public function getHeaders()
    {
    
        return array(); # TODO: implement this (although nobody currently uses this)
    }
    
    public function getHasResultRows()
    {
        return $this->count() > 0;
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
    
    /**
     * Alias of fetchArray
     * @return array
     */
    public function fetch()
    {
        return $this->fetchArray();
    }
    
    public function fetchArray()
    {
        
        $row = $this->fetchAssoc();
        
        $number = 0;
        foreach ($row as $value) {
            $row[$number] = $value;
            $number++;
        }
        
        return $row;
    }
    
    public function fetchAssoc()
    {
        
        $row = $this->current();
        $this->next();
        
        return $row;
    }
    
    public function fetchRow()
    {
        
        $row = $this->fetchAssoc();
        
        $returnRow = array();
        
        foreach ($row as $value) {
            $returnRow[] = $value;
        }
        
        return $returnRow;
    }
    
    private $schemaId;

    public function getSchemaId()
    {
        return $this->schemaId;
    }

    private $parameters = array();

    public function getParameters()
    {
        return $this->parameters;
    }

    private $statement;

    public function getStatement()
    {
        return $this->statement;
    }

    private $joinDefinition;

    public function getJoinDefinition()
    {
        return $this->joinDefinition;
    }

    private $rowCounter;

    private $tableResources = array();
    
    public function setTableResources(array $tableResources)
    {
        $this->tableResources = $tableResources;
        $this->initialized = true;
    }

    protected function init()
    {
        foreach ($this->getJoinDefinition()->getTables() as $alias => $joinTable) {
            /* @var $joinTable TableJoin */

            $tableSpecifier = $joinTable->getDataSource();

            if ($tableSpecifier instanceof ParenthesisPart) {
                $alias = $tableSpecifier->getAlias();
                $tableSpecifier = $tableSpecifier->getContain();
            }

            if (is_null($alias)) {
                $alias = (string)$tableSpecifier;
            }

            $tableResource = $this->executionContext->getTable($alias);

            ### TODO: implement a filter here to skip non-relevant rows in $tableResource

            $this->tableResources[$alias] = $tableResource;
        }
    }
    
    private $initialized = false;
    
    public function rewind()
    {
        
        if (!$this->initialized) {
            $this->initialized = true;
            $this->init();
        }
        
        foreach ($this->tableResources as $tableResource) {
            switch(true){
            
                case $tableResource instanceof Iterator:
                    $tableResource->rewind();
                    break;
            
                case $tableResource instanceof IteratorAggregate:
                    $tableResource->getIterator()->rewind();
                    break;
            
                default:
                    throw new ErrorException("Invalid table-source type!");
            }
                
        }
        
        $this->rowCounter = 0;
    }

    public function valid()
    {

        $tableResource = reset($this->tableResources);
        
        switch(true){

            case $tableResource instanceof Iterator:
                return $tableResource->valid();
                    
            case $tableResource instanceof IteratorAggregate:
                return $tableResource->getIterator()->valid();
                    
            case count($this->tableResources)>0:
                throw new ErrorException("Invalid table-source type!");
        }

        return false;
    }

    public function current()
    {

        $rows = array();

        foreach ($this->tableResources as $alias => $tableResource) {
            switch(true){

                case $tableResource instanceof Iterator:
                    $rows[$alias] = $tableResource->current();
                    break;

                case $tableResource instanceof IteratorAggregate:
                    $rows[$alias] = $tableResource->getIterator()->current();
                    break;

                default:
                    throw new ErrorException("Invalid table-source type!");
            }

            if (!is_array($rows[$alias])) {
                $type = get_class($tableResource);
                throw new ErrorException("Table-Resource '{$alias}' ({$type}) returned non-array as row!");
            }
            
            if ($tableResource instanceof UsesBinaryDataInterface && $tableResource->usesBinaryData()) {
                $rows[$alias] = $tableResource->convertDataRowToStringRow($rows[$alias]);
            }
        }

        $mergedRow = array();
        
        foreach ($rows as $alias => $row) {
            foreach ($row as $columnName => $cellData) {
                $mergedRow[$columnName] = $cellData;
                $mergedRow["{$alias}.{$columnName}"] = $cellData;
            }
        }
        
        return $mergedRow;
    }
    
    public function key()
    {
        return $this->rowCounter;
    }

    public function next()
    {
        
        $this->rowCounter++;
        
        if (!$this->valid()) {
            return;
        }
        
        $index = 0;
        foreach (array_reverse($this->tableResources) as $alias => $tableResource) {
            if ($tableResource instanceof IteratorAggregate) {
                $tableResource = $tableResource->getIterator();
            }

            $tableResource->next();
            
            if ($tableResource->valid()) {
                /* @var $table Table */
                $table = $this->getJoinDefinition()->getTables()[$index];
        
                if ($table->getIsInner()) {
                    // TODO: skip row when INNER and key is null
                }
                break;

            } else {
                $index++;
                if ($index < count($this->tableResources)) {
                    $tableResource->rewind();
                }
            }
            
        }
        
    }
    
    public function seek($rowId)
    {

        foreach ($this->tableResources as $alias => $tableResource) {
            $count = $tableResource->count();
            
            if ($count>0) {
                $tableResource->seek($rowId % $count);
                $rowId = floor($rowId / $count);
            }
        }
    }
    
    public function count()
    {
        
        $count = 1;
        
        foreach ($this->tableResources as $alias => $tableResource) {
            $count *= $tableResource->count();
        }
        
        return $count;
    }
    
    public function getIterator()
    {
        return $this;
    }
    
    public function getUnsortedIterator()
    {
        
        $unsortedJoinIterator = new JoinIterator(
            $this->joinDefinition,
            $this->executionContext,
            $this->selectExecutor,
            $this->valueResolver
        );

        $tableResources = array();

        if (count($this->tableResources)<=0) {
            $this->init();
        }
        
        $unsortedJoinIterator->setTableResources($this->tableResources);
        
        return $unsortedJoinIterator;
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

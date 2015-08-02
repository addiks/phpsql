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
use Addiks\PHPSQL\Table;
use Addiks\PHPSQL\SortedResourceIterator;
use Addiks\PHPSQL\Entity\Job\Statement\SelectStatement;
use Addiks\PHPSQL\Entity\Result\ResultInterface;
use Addiks\PHPSQL\CustomIterator;
use ErrorException;
use Addiks\PHPSQL\Database;
use Addiks\PHPSQL\Table\TableContainer;
use Addiks\PHPSQL\StatementExecutor\SelectExecutor;
use Addiks\PHPSQL\ValueResolver;

/**
 * The purpose of this component is to cross-join in any needed way
 * between multiple data-sources (tables, resultsets, indexes, ...).
 */
class JoinIterator implements \SeekableIterator, \Countable, ResultInterface
{

    public function __construct(
        TableContainer $tableContainer,
        SelectExecutor $selectExecutor,
        ValueResolver $valueResolver,
        SelectStatement $statement,
        $schemaId = null,
        array $parameters = array()
    ) {
        $this->tableContainer = $tableContainer;
        $this->selectExecutor = $selectExecutor;
        $this->valueResolver = $valueResolver;
        $this->statement = $statement;
        $this->schemaId = $schemaId;
        $this->parameters = $parameters;
    }

    protected $valueResolver;

    protected $tableContainer;

    public function gettableContainer()
    {
        return $this->tableContainer;
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

    public function getJoinDefinition()
    {
        return $this->getStatement()->getJoinDefinition();
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
        
        $this->tableResources = array();
        foreach ($this->getJoinDefinition()->getTables() as $table) {
            /* @var $table Table */
        
            /* @var $parenthesis Parenthesis */
            $parenthesis = $table->getDataSource();
        
            $alias      = $parenthesis->getAlias();
            $dataSource = $parenthesis->getContain();
        
            switch(true){
        
                case $dataSource instanceof TableSpecifier:
        
                    if (strlen($alias)<=0) {
                        $alias = (string)$dataSource;
                    }
        
                    if (!is_null($dataSource->getDatabase())) {
                        $databaseId = $dataSource->getDatabase();
        
                    } else {
                        $databaseId = $this->schemaId;
                    }
        
                    /* @var $tableResource Table */
                    $tableResource = $this->tableContainer->getTable(
                        $dataSource->getTable(),
                        $databaseId
                    );
        
                    $resourceIterator = new SortedResourceIterator(
                        $tableResource,
                        $this->valueResolver
                    );
            
                    if (count($this->getStatement()->getOrderColumns())>0) {
                        $orderColumns = $this->getStatement()->getOrderColumns();
                        
                        $resourceIterator->setTemporaryBuildChildIteratorByValue($orderColumns, $this);
                    }
                    
                    if (is_null($resourceIterator->getChildIterator())) {
                        /* @var $tableSchema TableSchema */
                        $tableSchema = $tableResource->getTableSchema();
        
                        $primaryKeyColumns = $tableSchema->getPrimaryKeyColumns();
        
                        $primaryIndexId = $tableSchema->getIndexIdByColumns(array_keys($primaryKeyColumns));
        
                        if (!is_null($primaryIndexId)) {
                            /* @var $index Index */
                            $index = $tableResource->getIndex($primaryIndexId);
                                
                            // TODO: try to extract begin/end values from conditions
                            $beginValue = null;
                            $endValue = null;
                                
                            $iterator = $index->getIterator($beginValue, $endValue);
                            
                            $resourceIterator->setChildIterator($iterator);
                                
                        } else {
                            // no index usable, build temporary index using insertion-sort
                            
                            /* @var $sortIndex Quicksort */
                            $sortIndex = $resourceIterator->getSortIndexByColumns($primaryKeyColumns);
                            
                            foreach ($tableResource as $rowId => $row) {
                                $sortIndex->addRow($rowId, $row);
                            }
                            $sortIndex->sort();
                                
                            $resourceIterator->setChildIterator($sortIndex);
                        }
        
                    }
        
                    $this->tableResources[$alias] = $resourceIterator;
                    break;
        
                case $dataSource instanceof Select:
        
                    print((string)$dataSource);
                    
                    /* @var $result SelectResult */
                    $result = $this->selectExecutor->executeJob($dataSource, $this->getParameters());
        
                    $this->tableResources[$alias] = $result;
                    break;
        
            }
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
            
                case $tableResource instanceof SortedResourceIterator:
                    $tableResource->getIterator()->rewind();
                    break;
            
                case $tableResource instanceof Table:
                    $tableResource->getIterator()->rewind();
                    break;
            
                case $tableResource instanceof SelectResult:
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

            case $tableResource instanceof SortedResourceIterator:
                return $tableResource->getIterator()->valid();
                    
            case $tableResource instanceof Table:
                return $tableResource->getIterator()->valid();
                    
            case $tableResource instanceof SelectResult:
                return $tableResource->getIterator()->valid();
                
            default:
                throw new ErrorException("Invalid table-source type!");
        }
    }

    public function current()
    {

        $rows = array();

        foreach ($this->tableResources as $alias => $tableResource) {
            switch(true){

                case $tableResource instanceof SortedResourceIterator:
                    $rows[$alias] = $tableResource->current();
                    break;

                case $tableResource instanceof Table:
                    $rows[$alias] = $tableResource->getIterator()->current();
                    if (is_array($rows[$alias])) {
                        $rows[$alias] = $tableResource->convertDataRowToStringRow($rows[$alias]);
                    }
                    break;

                case $tableResource instanceof SelectResult:
                    $rows[$alias] = current($tableResource);
                    break;

                default:
                    throw new ErrorException("Invalid table-source type!");
            }
            
            if (!is_array($rows[$alias])) {
                throw new ErrorException("Table-Resource '{$alias}' returned non-array as row!");
            }
        }

        return $rows;
    }
    
    public function getMergedCurrentRow()
    {

        $mergedRow = array();
        
        foreach ($this->current() as $alias => $row) {
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
            $tableResource->getIterator()->next();
            
            if ($tableResource->getIterator()->valid()) {
                /* @var $table Table */
                $table = $this->getJoinDefinition()->getTables()[$index];
        
                // TODO: skip row when INNER and key is null
                if ($table->getIsInner()) {
                    break;
                } else {
                    break;
                }
            } else {
                $index++;
                if ($index < count($this->tableResources)) {
                    $tableResource->getIterator()->rewind();
                }
            }
            
        }
        
    }
    
    public function seek($rowId)
    {

        foreach ($this->tableResources as $alias => $tableResource) {
            $count = $tableResource->count();
            
            $tableResource->seek($rowId % $count);
            
            $rowId = floor($rowId / $count);
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
            $this->gettableContainer(),
            $this->getDatabase(),
            $this->getStatement(),
            $this->getSchemaId(),
            $this->getParameters()
        );

        $tableResources = array();
        
        foreach ($this->tableResources as $alias => $tableResource) {
            switch(true){
            
                case $tableResource instanceof SortedResourceIterator:
                    $tableResources[$alias] = $tableResource->getResource();
                    break;
            
                case $tableResource instanceof Table:
                    $tableResources[$alias] = $tableResource;
                    break;
            
                case $tableResource instanceof SelectResult:
                    $tableResources[$alias] = $tableResource;
                    break;
            
                default:
                    throw new ErrorException("Invalid table-source type!");
            }
                
        }
        
        $unsortedJoinIterator->setTableResources($tableResources);
        
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

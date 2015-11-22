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

namespace Addiks\PHPSQL\Iterators;

use Addiks\PHPSQL\Value\Specifier\TableSpecifier;
use Addiks\PHPSQL\Iterators\SortedResourceIterator;
use Addiks\PHPSQL\Job\Statement\SelectStatement;
use Addiks\PHPSQL\Result\ResultInterface;
use ErrorException;
use SeekableIterator;
use Countable;
use IteratorAggregate;
use Iterator;
use Addiks\PHPSQL\Database\Database;
use Addiks\PHPSQL\StatementExecutor\SelectExecutor;
use Addiks\PHPSQL\ValueResolver\ValueResolver;
use Addiks\PHPSQL\StatementExecutor\ExecutionContext;
use Addiks\PHPSQL\Job\Part\Join;
use Addiks\PHPSQL\Job\Part\Join\TableJoin;
use Addiks\PHPSQL\Job\Part\ParenthesisPart;
use Addiks\PHPSQL\Iterators\FilteredResourceIterator;
use Addiks\PHPSQL\Iterators\UsesBinaryDataInterface;
use Addiks\PHPSQL\Job\Part\ColumnDefinition;
use Addiks\PHPSQL\BinaryConverterTrait;
use Addiks\PHPSQL\Iterators\DataProviderInterface;

/**
 * The purpose of this component is to cross-join in any needed way
 * between multiple data-sources (tables, resultsets, indexes, ...).
 */
class JoinIterator implements DataProviderInterface
{
    use BinaryConverterTrait;

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

    private $rowPath = array();

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

            /* @var $tableResource DataProviderInterface */
            $tableResource = $this->executionContext->getTable($alias);

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

        $rowPath = array();
        foreach ($this->tableResources as $tableResource) {
            $tableResource->rewind();
            if ($tableResource->valid()) {
                $rowPath[] = $tableResource->tell();
            }
        }

        $this->rowPath = $rowPath;
        $this->rowCounter = 0;
    }

    public function valid()
    {
        $tableResource = reset($this->tableResources);
        return $tableResource->valid();
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
        $rowPath = implode(":", $this->rowPath);
        $rowPath = $this->strdec($rowPath);
        return $rowPath;
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
            $this->rowPath[$index] = $tableResource->tell();

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
                $this->rowPath[$index-1] = $tableResource->tell();
            }

        }

    }

    public function seek($rowPath)
    {
        $rowPath = $this->decstr($rowPath);
        $rowPathArray = explode(':', $rowPath);

        $index = 0;
        foreach (array_reverse($this->tableResources) as $alias => $tableResource) {
            $tableResource->seek((int)$rowPathArray[$index]);
            $index++;
        }
    }

    public function count()
    {
        $count = 1;

        foreach ($this->tableResources as $alias => $tableResource) {
            # TODO: give better count taking INNER/OUTER-JOIN with NULL values in consideration.
            $count *= $tableResource->count();
        }

        return $count;
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

    public function doesRowExists($rowId = null)
    {
        if (is_null($rowId)) {
            $rowId = $this->tell();
        }
        return $rowId < $this->count();
    }

    public function tell()
    {
        return $this->key();
    }

    public function getTableSchema()
    {
        throw new ErrorException("UNIMPLEMENTED");
        # TODO: implement this!
    }

    public function getRowData($rowIndex = null)
    {
        if (is_null($rowId)) {
            $rowId = $this->tell();
        }

        $beforeSeek = $this->tell();

        $this->seek($rowIndex);

        $row = $this->current();

        $this->seek($beforeSeek);

        return $row;
    }

    public function getCellData($rowId, $columnId)
    {
        $row = $this->getRowData();

        return $row[$columnId];
    }

}

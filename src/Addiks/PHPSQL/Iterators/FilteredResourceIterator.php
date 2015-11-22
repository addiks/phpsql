<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\Iterators;

use Iterator;
use SeekableIterator;
use Countable;
use Addiks\PHPSQL\Job\Part\ValuePart;
use Addiks\PHPSQL\ValueResolver\ValueResolver;
use Addiks\PHPSQL\Result\ResultInterface;
use Addiks\PHPSQL\Table\TableInterface;
use Addiks\PHPSQL\StatementExecutor\ExecutionContext;
use Addiks\PHPSQL\Iterators\DataProviderInterface;
use Addiks\PHPSQL\Iterators\UsesBinaryDataInterface;
use Addiks\PHPSQL\Index\IndexInterface;
use Addiks\PHPSQL\Value\Specifier\ColumnSpecifier;
use Addiks\PHPSQL\Job\Part\ConditionJob;
use Addiks\PHPSQL\Value\Enum\Sql\Operator;
use Addiks\PHPSQL\BinaryConverterTrait;
use Addiks\PHPSQL\Column\ColumnSchema;
use Addiks\PHPSQL\Job\Part;

class FilteredResourceIterator implements DataProviderInterface, UsesBinaryDataInterface
{
    use BinaryConverterTrait;

    public function __construct(
        DataProviderInterface $tableResource,
        ValuePart $condition,
        ValueResolver $valueResolver,
        ExecutionContext $executionContext,
        IndexInterface $index = null,
        array $indexedColumns = null
    ) {
        $this->tableResource = $tableResource;
        $this->condition = $condition;
        $this->valueResolver = $valueResolver;
        $this->executionContext = clone $executionContext;
        $this->index = $index;
        $this->indexedColumns = $indexedColumns;

        if (!is_null($index) && !is_null($indexedColumns)) {
            $this->initIndexIterator();
        }
    }

    /**
     * @var DataProviderInterface
     */
    protected $tableResource;

    /**
     * @var ValuePart
     */
    protected $condition;

    /**
     * @var ValueResolver
     */
    protected $valueResolver;

    /**
     * @var ExecutionContext
     */
    protected $executionContext;

    /**
     * @var IndexInterface
     */
    protected $index;

    /**
     * @var Iterator
     */
    protected $indexIterator;

    public function rewind()
    {
        if (!is_null($this->index)) {
            $this->indexIterator->rewind();
            if ($this->indexIterator->valid()) {
                $this->alignTableResourceToIndex();
            }

        } else {
            $this->tableResource->rewind();
            $this->skipNotMatchingRows();
        }
    }

    public function valid()
    {
        if (!is_null($this->index)) {
            return $this->indexIterator->valid();

        } else {
            return $this->tableResource->valid();
        }
    }

    public function current()
    {
        return $this->tableResource->current();
    }

    public function key()
    {
        return $this->tableResource->key();
    }

    public function next()
    {
        if (!is_null($this->index)) {
            $this->indexIterator->next();
            if ($this->indexIterator->valid()) {
                $this->alignTableResourceToIndex();
            }

        } else {
            $this->tableResource->next();
        }
        $this->skipNotMatchingRows();
    }

    public function seek($position)
    {
        $this->tableResource->seek($position);
    }

    public function count()
    {
        return $this->tableResource->count();
    }

    public function tell()
    {
        return $this->tableResource->tell();
    }

    public function doesRowExists($rowId = null)
    {
        return $this->tableResource->doesRowExists($rowId);
    }

    public function getTableSchema()
    {
        return $this->tableResource->getTableSchema();
    }

    public function getRowData($rowId = null)
    {
        return $this->tableResource->getRowData($rowId);
    }

    public function getCellData($rowId, $columnId)
    {
        $row = $this->getRowData();

        return $row[$columnId];
    }

    private function skipNotMatchingRows()
    {
        while ($this->valid() && !$this->doesCurrentRowPassesFilters()) {
            if (!is_null($this->index)) {
                $this->indexIterator->next();
                if ($this->indexIterator->valid()) {
                    $this->alignTableResourceToIndex();
                }

            } else {
                $this->tableResource->next();
            }
        }
    }

    private function alignTableResourceToIndex()
    {
        $rowId = $this->indexIterator->current();

        if ($this->usesBinaryData()) {
            $rowId = $this->strdec($rowId);
        }

        if (is_int($rowId)) {
            $this->tableResource->seek($rowId);
        }
    }

    private function doesCurrentRowPassesFilters()
    {
        $row = $this->current();

        $result = false;

        if (is_array($row)) {
            if ($this->usesBinaryData()) {
                $row = $this->convertDataRowToStringRow($row);
            }

            $this->executionContext->setCurrentSourceRow($row);

            $result = $this->valueResolver->resolveValue($this->condition, $this->executionContext);
        }

        return (bool)$result;
    }

    public function usesBinaryData()
    {
        $isBinary = false;
        if ($this->tableResource instanceof UsesBinaryDataInterface) {
            $isBinary = $this->tableResource->usesBinaryData();
        }
        return $isBinary;
    }

    public function convertDataRowToStringRow(array $row)
    {
        if ($this->tableResource instanceof UsesBinaryDataInterface) {
            $row = $this->tableResource->convertDataRowToStringRow($row);
        }
        return $row;
    }

    public function convertStringRowToDataRow(array $row)
    {
        if ($this->tableResource instanceof UsesBinaryDataInterface) {
            $row = $this->tableResource->convertStringRowToDataRow($row);
        }
        return $row;
    }

    ### HELPER

    protected function initIndexIterator()
    {
        $condition = $this->condition;

        $tableConditions = $this->findFixedConditions($condition);

        $beginValues = array();
        $endValues = array();

        foreach (array_keys($this->indexedColumns) as $columnId) {
            $beginValues[$columnId] = null;
            $endValues[$columnId] = null;
        }

        foreach ($tableConditions as $tableCondition) {
            /* @var $tableCondition ConditionJob */

            $column = $tableCondition->getFirstParameter();
            $fixedValue = $tableCondition->getLastParameter();

            if ($fixedValue instanceof ColumnSpecifier) {
                list($column, $fixedValue) = [$fixedValue, $column];
            }

            if ($column instanceof ColumnSpecifier) {
                $columnName = $column->getColumn();
                $columnId   = array_search($columnName, $this->indexedColumns);
                if (array_key_exists($columnId, $beginValues)) {
                    $value = $this->valueResolver->resolveValue($fixedValue, $this->executionContext);
                    if (in_array($tableCondition->getOperator(), [
                        Operator::OP_EQUAL,
                        Operator::OP_EQUAL_NULLSAFE,
                        Operator::OP_BETWEEN,
                        Operator::OP_GREATEREQUAL,
                    ])) {
                        $beginValues[$columnId] = $value;
                    }
                    if (in_array($tableCondition->getOperator(), [
                        Operator::OP_GREATER,
                    ])) {
                        if (is_numeric($value)) {
                            $beginValues[$columnId] = $value + 1;
                        } else {
                            $this->stringIncrement($value);
                            $beginValues[$columnId] = $value;
                        }
                    }
                    if (in_array($tableCondition->getOperator(), [
                        Operator::OP_EQUAL,
                        Operator::OP_EQUAL_NULLSAFE,
                        Operator::OP_BETWEEN,
                        Operator::OP_LESSEREQUAL,
                    ])) {
                        $endValues[$columnId] = $value;
                    }
                    if (in_array($tableCondition->getOperator(), [
                        Operator::OP_LESSER,
                    ])) {
                        if (is_numeric($value)) {
                            $endValues[$columnId] = $value - 1;
                        } else {
                            $this->stringDecrement($value);
                            $endValues[$columnId] = $value;
                        }
                    }
                }
            }
        }

        if ($this->usesBinaryData()) {
            $beginValues = $this->convertStringRowToDataRow($beginValues);
            $endValues   = $this->convertStringRowToDataRow($endValues);
        }

        $this->indexIterator = $this->index->getIterator($beginValues, $endValues);
    }

    /**
     * Extracts part(s) of a (complicated) condition that map a column of the given table directly to a fixed value.
     *  (E.g.: "(foo.a > bar.b && baz.c = 1) && (far.d = faz.e || far.f = 3)" results in "baz.c = 1" for table "baz")
     *
     * Only considers the parts that can directly negate the condition result (those liked with AND operators).
     *
     * @param  ValuePart      $condition
     * @return array          (array of ValuePart)
     */
    protected function findFixedConditions(
        Part $condition
    ) {
        $tableConditions = array();

        if ($condition instanceof ValuePart) {
            foreach ($condition->getChainValues() as $chainValue) {
                if ($chainValue instanceof Part) {
                    $tableConditions = array_merge(
                        $tableConditions,
                        $this->findFixedConditions($chainValue)
                    );
                }
            }

        } elseif ($condition instanceof ConditionJob) {
            /* @var $condition ConditionJob */

            if ($condition->getOperator() === Operator::OP_AND()) {
                foreach ([
                    $condition->getFirstParameter(),
                    $condition->getLastParameter()
                ] as $conditionParameter) {
                    if ($conditionParameter instanceof Part) {
                        $tableConditions = array_merge(
                            $tableConditions,
                            $this->findFixedConditions($conditionParameter)
                        );
                    }
                }

            } elseif (in_array($condition->getOperator(), [
                Operator::OP_EQUAL(),
                Operator::OP_EQUAL_NULLSAFE(),
                Operator::OP_NOT_EQUAL(),
                Operator::OP_GREATER(),
                Operator::OP_GREATEREQUAL(),
                Operator::OP_LESSER(),
                Operator::OP_LESSEREQUAL(),
                Operator::OP_BETWEEN(),
                Operator::OP_NOT_BETWEEN(),
            ])) {
                $column = $condition->getFirstParameter();
                $fixedValue = $condition->getLastParameter();

                if ($fixedValue instanceof ColumnSpecifier) {
                    list($column, $fixedValue) = [$fixedValue, $column];
                }

                if ($column instanceof ColumnSpecifier
                 && $this->isFixedValue($fixedValue)) {
                    $tableConditions[] = $condition;
                }
            }
        }

        return $tableConditions;
    }

    private function isFixedValue($fixedValue)
    {
        $isFixedValue = false;

        if (is_scalar($isFixedValue)) {
            $isFixedValue = true;

        } elseif ($fixedValue instanceof ValuePart) {
            $isFixedValue = true;

            foreach ($fixedValue->getChainValues() as $chainValue) {
                $isFixedValue = ($isFixedValue && $this->isFixedValue($chainValue));
            }
        }

        return $isFixedValue;
    }

}

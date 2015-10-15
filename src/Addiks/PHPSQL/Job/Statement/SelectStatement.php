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

namespace Addiks\PHPSQL\Job\Statement;

use Addiks\PHPSQL\Result\Specifier;
use Addiks\PHPSQL\Job\Part\Join;
use Addiks\PHPSQL\Job\StatementJob;
use Addiks\PHPSQL\Executor\SelectExecutor;
use Addiks\PHPSQL\Exception\MalformedSqlException;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\Job\FunctionJob;
use ErrorException;
use Addiks\PHPSQL\Value\Specifier\ColumnSpecifier;
use Addiks\PHPSQL\Value\Specifier\TableSpecifier;
use Addiks\PHPSQL\Job\Part\ValuePart;
use Addiks\PHPSQL\Result\Specifier\ResultColumnSpecifier as Column;
use Addiks\PHPSQL\Job\Statement\SelectStatement;
use Addiks\PHPSQL\Job\Part\GroupingDefinition;
use Addiks\PHPSQL\Result\ResultSpecifier;
use Addiks\PHPSQL\Result\Specifier\ResultColumnSpecifier;

/**
 *
 */
class SelectStatement extends StatementJob
{

    const EXECUTOR_CLASS = SelectExecutor::class;

    private $columns = array();
    
    public function getColumns()
    {
        return $this->columns;
    }
    
    public function addColumnSubQuery(Select $subSelect, $alias)
    {
        if (isset($this->columns[$alias])) {
            throw new MalformedSqlException("Column '{$alias}' already defined!");
        }
        $this->columns[$alias] = $subSelect;
    }
    
    public function addColumnValue($value, $alias = null)
    {
        
        if (is_object($value) && method_exists($value, 'setAlias')) {
            $value->setAlias($alias);
        }
        
        switch(true){
            
            case $value instanceof ValuePart:
                if (is_null($alias)) {
                    $alias = $value->generateAlias();
                }
                $this->columns[$alias] = $value;
                break;
            
            case $value instanceof ColumnSpecifier:
                $this->addColumnSpecifier($value, $alias);
                break;
                
            case $value instanceof TableSpecifier:
                $this->addColumnAllTable($value, $alias);
                break;
                
            case $value instanceof Variable:
                $this->addColumnVariable($value, $alias);
                break;
                
            case $value instanceof FunctionJob:
                $this->addColumnFunction($value, $alias);
                break;
                
            case $value instanceof Parenthesis:
                $this->addColumnParenthesis($value, $alias);
                break;
                
            case $value instanceof Condition:
                $this->addColumnCondition($value, $alias);
                break;
                
            case is_int($value) || is_float($value) || is_numeric($value):
                $this->addColumnNumber($value, $alias);
                break;
                
            case $value instanceof Token:
                $this->addColumnSqlToken($value, $alias);
                break;
                
            case is_string($value):
                $this->addColumnString($value, $alias);
                break;

            default:
                if (is_object($value)) {
                    $descriptor = get_class($value);
                } else {
                    $descriptor = get_type($value);
                }
                throw new ErrorException("Unexpected type of column! ({$descriptor})");
        }
    }
    
    public function addColumnSpecifier(ColumnSpecifier $column, $alias = null)
    {
        if (is_null($alias)) {
            $alias = $column->getColumn();
        }
        if (isset($this->columns[$alias])) {
            throw new MalformedSqlException("Column alias '{$alias}' already defined!");
        }
        $this->columns[$alias] = $column;
    }
    
    public function addColumnAllTable(TableSpecifier $tableFilter = null, $alias = null)
    {
        if (is_null($alias)) {
            $alias = is_null($tableFilter) ?'*' :$tableFilter->getValue();
        }
        if (isset($this->columns[$alias])) {
            throw new MalformedSqlException("Column alias '{$alias}' already defined!");
        }
        $this->columns[$alias] = is_null($tableFilter) ? '*' : $tableFilter;
    }
    
    public function addColumnNumber($number, $alias = null)
    {
        if (is_null($alias)) {
            $alias = (string)$number;
        }
        if (isset($this->columns[$alias])) {
            throw new MalformedSqlException("Column alias '{$alias}' already defined!");
        }
        $this->columns[$alias] = $number;
    }
    
    public function addColumnString($string, $alias = null)
    {
        if (is_null($alias)) {
            $alias = $string;
        }
        if (isset($this->columns[$alias])) {
            throw new MalformedSqlException("Column alias '{$alias}' already defined!");
        }
        $this->columns[$alias] = $string;
    }
    
    public function addColumnVariable(Variable $variable, $alias = null)
    {
        if (is_null($alias)) {
            $alias = $variable->getValue();
        }
        if (isset($this->columns[$alias])) {
            throw new MalformedSqlException("Column alias '{$alias}' already defined!");
        }
        $this->columns[$alias] = $variable;
    }
    
    public function addColumnFunction(FunctionJob $function, $alias = null)
    {
        if (is_null($alias)) {
            $i = 0;
            do {
                $i++;
                $alias = "{$function->getName()}#{$i}";
            } while (isset($this->columns[$alias]));
        }
        if (isset($this->columns[$alias])) {
            throw new MalformedSqlException("Column alias '{$alias}' already defined!");
        }
        $this->columns[$alias] = $function;
    }
    
    public function addColumnParenthesis(Parenthesis $parenthesis, $alias = null)
    {
        if (is_null($alias)) {
            $i = 0;
            do {
                $i++;
                $alias = "PARENTHESIS#{$i}";
            } while (isset($this->columns[$alias]));
        }
        if (isset($this->columns[$alias])) {
            throw new MalformedSqlException("Column alias '{$alias}' already defined!");
        }
        $this->columns[$alias] = $parenthesis;
    }
    
    public function addColumnCondition(Condition $condition, $alias = null)
    {
        if (is_null($alias)) {
            $i = 0;
            do {
                $i++;
                $alias = "CONDITION#{$i}";
            } while (isset($this->columns[$alias]));
        }
        if (isset($this->columns[$alias])) {
            throw new MalformedSqlException("Column alias '{$alias}' already defined!");
        }
        $this->columns[$alias] = $condition;
    }
    
    public function addColumnSqlToken(Token $token, $alias = null)
    {
        if (is_null($alias)) {
            $i = 0;
            $tokenName = $token->getName();
            $tokenName = substr($tokenName, 2);
            do {
                $i++;
                $alias = "{$tokenName}#{$i}";
            } while (isset($this->columns[$alias]));
        }
        if (isset($this->columns[$alias])) {
            throw new MalformedSqlException("Column alias '{$alias}' already defined!");
        }
        $this->columns[$alias] = $token;
    }
    
    private $joinDefinition;
    
    public function setJoinDefinition(Join $join)
    {
        $this->joinDefinition = $join;
    }
    
    public function getJoinDefinition()
    {
        return $this->joinDefinition;
    }
    
    /**
     * Defines what flags are set for this select.
     * @see self::FLAG_*
     * @var int
     */
    private $specialFlags = 0;
    
    /**
     * This adds one flag to the current flags.
     * @see self::FLAG_*
     * @param int $flagValue
     */
    public function addFlag(SpecialFlags $flag)
    {
        $this->specialFlags = $this->specialFlags | $flag->getValue();
    }
    
    /**
     * Checks if a given flag is set on select statement.
     * @param SpecialFlags $flag
     */
    public function hasFlagSet(SpecialFlags $flag)
    {
        return ($this->specialFlags & $flag->getValue() === $flag->getValue());
    }
    
    /**
     * gets the flags value (as integer).
     * @return int
     */
    public function getFlags()
    {
        return $this->specialFlags;
    }
    
    public function getResultSpecifier()
    {
    
        $resultSpecifier = new ResultSpecifier();
        $resultColumn = new ResultColumnSpecifier();
        
        foreach ($this->getColumns() as $alias => $column) {
            switch(true){
                    
                case $column instanceof Column:
                    $resultColumn->setSchemaSourceColumn($column);
                    $resultColumn->setName($column->getColumn());
                    break;
                    
                case $column === '*':
                    $resultColumn->setSchemaSourceJoker();
                    $resultColumn->setIsAllColumnsFromTable(true);
                    break;
                    
                case $column instanceof Table:
                    $resultColumn->setSchemaSourceTable($column);
                    $resultColumn->setIsAllColumnsFromTable(true);
                    break;
                    
                case $column instanceof Select:
                    $Specifier = $column->getResultSpecifier();
                    if (count($Specifier->getColumns())!==1) {
                        throw new MalformedSqlException("Wrong column count in sub-select!");
                    }
                    $resultColumn = reset($Specifier->getColumns());
                    break;
                    
                case $column instanceof ValuePart:
                    $resultColumn->setSchemaSourceValue($column);
                    break;
            }
            
            if (is_string($alias)) {
                $resultColumn->setName($alias);
            }
            
            $resultSpecifier->addColumn(clone $resultColumn);
        }
    
        return $resultSpecifier;
    }
    
    private $condition;
    
    public function setCondition(ValuePart $condition)
    {
        $this->condition = $condition;
    }
    
    public function getCondition()
    {
        return $this->condition;
    }
    
    private $orderColumns = array();
    
    public function addOrderColumn($value, $direction)
    {
        $this->orderColumns[] = [
            'value' => $value,
            'direction' => (SqlToken::T_ASC() ?$direction :SqlToken::T_DESC())
        ];
    }
    
    public function getOrderColumns()
    {
        return $this->orderColumns;
    }
    
    private $limitOffset;
    
    public function setLimitOffset($offset)
    {
        $this->limitOffset = (int)$offset;
    }
    
    public function getLimitOffset()
    {
        return $this->limitOffset;
    }
    
    private $limitRowCount;
    
    public function setLimitRowCount($count)
    {
        $this->limitRowCount = (int)$count;
    }
    
    public function getLimitRowCount()
    {
        return $this->limitRowCount;
    }
    
    private $groupings = array();
    
    public function addGrouping(GroupingDefinition $grouping)
    {
        $this->groupings[] = $grouping;
    }
    
    public function getGroupings()
    {
        return $this->groupings;
    }
    
    private $isForUpdate = false;
    
    public function setIsForUpdate($bool)
    {
        $this->isForUpdate = (bool)$bool;
    }
    
    public function getIsForUpdate()
    {
        return $this->isForUpdate;
    }
    
    private $isLockInShareMode = false;
    
    public function setIsLockInShareMode($bool)
    {
        $this->isLockInShareMode = (bool)$bool;
    }
    
    public function getIsLockInShareMode()
    {
        return $this->isLockInShareMode;
    }
    
    private $outFilePath;
    
    public function setIntoOutFile($outFilePath)
    {
        $this->outFilePath = (string)$outFilePath;
    }
    
    public function getIntoOutFile()
    {
        return $this->outFilePath;
    }
    
    private $procedure;
    
    public function setProcedure(FunctionJob $procedure)
    {
        $this->procedure = $procedure;
    }
    
    public function getProcedure()
    {
        return $this->procedure;
    }
    
    private $unionSelect;
    
    public function setUnionSelect(SelectStatement $select, $isDistinct = false)
    {
        if ($isDistinct) {
        }
        $this->unionSelect = $select;
    }
    
    public function getUnionSelect()
    {
        return $this->unionSelect;
    }
    
    private $resultFilter;
    
    public function setResultFilter($value)
    {
        $this->resultFilter = $value;
    }
    
    public function getResultFilter()
    {
        return $this->resultFilter;
    }
}

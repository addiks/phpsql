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

namespace Addiks\PHPSQL\Service;

use Addiks\PHPSQL\Value\Enum\Sql\Operator;
use Addiks\PHPSQL\Value\Specifier\ColumnSpecifier;
use Addiks\PHPSQL\Entity\Job\Part\Condition\Enum;
use Addiks\PHPSQL\Entity\Job\Part\Condition\Like;
use Addiks\PHPSQL\Entity\Job\Part\Parenthesis;
use Addiks\PHPSQL\Value\Sql\Variable;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\Entity\Job\Part\ConditionJob;
use Addiks\PHPSQL\Entity\Result\Specifier\Column;
use Addiks\PHPSQL\Entity\Job\Part\Value;
use Addiks\PHPSQL\Service\ValueResolver\FunctionResolver;
use Addiks\PHPSQL\Entity\Job\FunctionJob;
use Addiks\PHPSQL\Entity\Job\Part\FlowControl\CaseData;
use Addiks\PHPSQL\Entity\Result\ResultInterface;
use Addiks\PHPSQL\Entity\Job\Statement;
use ErrorException;

/**
 * This service can resolve any Value-Object into an scalar value.
 * It executes functions and conditions, get column-values, get parameter-values, etc...
 *
 * @author gerrit
 *
 */
class ValueResolver
{
    
    public function resolveSourceRow(array $row)
    {
        
        $this->setSourceRow($row);
        
        /* @var $statement Statement */
        $statement = $this->getStatement();
        
        $resultRow = array();
        
        $this->resetParameterCurrentIndex();
        
        foreach ($statement->getResultSpecifier() as $resultColumn) {
            /* @var $resultColumn Column */
            
            $this->resolveResultColumn($resultColumn, $resultRow);
            
        }
        
        return $resultRow;
    }
    
    ### INPUT
    
    private $parameters = array();
    
    public function setStatementParameters(array $parameters)
    {
        $this->parameters = $parameters;
    }
    
    public function getStatementParameters()
    {
        return $this->parameters;
    }
    
    private $sourceRow = array();
    
    public function setSourceRow(array $row)
    {
        $this->sourceRow = $row;
    }
    
    public function getSourceRow()
    {
        return $this->sourceRow;
    }
    
    private $statement;
    
    public function setStatement(Statement $statement)
    {
        $this->statement = $statement;
    }
    
    public function getStatement()
    {
        return $this->statement;
    }
    
    private $resultSet;
    
    public function setResultSet(ResultInterface $resultSet)
    {
        $this->resultSet = $resultSet;
    }
    
    public function getResultSet()
    {
        return $this->resultSet;
    }
    
    /**
     *
     * @return Column
     */
    public function getCurrentColumnSchema()
    {
        return $this->getTableSchema()->getColumn($this->getCurrentColumnId());
    }
    
    ### SUB-RESOLVER
    
    /**
     *
     * @return array
     * @param Column $resultColumn
     */
    public function resolveResultColumn(Column $resultColumn, array &$resultRow = array())
    {
        
        $schemaSource = $resultColumn->getSchemaSource();
        
        switch(true){
            
            case $schemaSource instanceof Value:
                
                $columnIdentifier = $schemaSource->generateAlias();
                
                $columnData = $this->resolveValue($schemaSource);
                
                $resultRow[$columnIdentifier] = $columnData;
                break;
                
            case $schemaSource instanceof Column:
                
                $columnIdentifier = (string)$schemaSource;
                
                $columnData = $this->getSourceRow()[$columnIdentifier];
                
                $resultRow[$columnIdentifier] = $columnData;
                break;
            
            case $resultColumn->getIsAllColumnsFromTable() && $schemaSource === '*':
                
                $tables = $this->getStatement()->getJoinDefinition()->getTables();
                
                if (count($tables) === 1) {
                    foreach ($this->getSourceRow() as $alias => $columnData) {
                        if (substr_count($alias, '.')===0) {
                            $resultRow[$alias] = $columnData;
                        }
                    }
                    
                } else {
                    foreach ($this->getSourceRow() as $alias => $columnData) {
                        if (substr_count($alias, '.')===1) {
                            $resultRow[$alias] = $columnData;
                        }
                    }
                }
                break;
            
            case $resultColumn->getIsAllColumnsFromTable() && $schemaSource instanceof Table:
                
                $needleAlias = (string)$schemaSource;
                
                foreach ($this->getSourceRow() as $alias => $columnData) {
                    if (substr($alias, 0, strlen($needleAlias)) === $needleAlias) {
                        $columnName = substr($alias, strlen($needleAlias)+1);
                        $resultRow[$columnName] = $columnData;
                    }
                }
                
                break;
                
            default:
                throw new \ErrorException("Invalid schema-source for result-specifier-column!");
        }
        
        return $resultRow;
    }
    
    private $parameterCurrentIndex = 0;
    
    public function resetParameterCurrentIndex()
    {
        $this->parameterCurrentIndex = 0;
    }
    
    public function resolveValue($value)
    {
        
        $returnValue = null;
        
        switch(true){
            
            case $value instanceof Value:
                $returnValue = $this->resolveValueJob($value);
                break;
            
            case $value instanceof Enum:
                $returnValue = $this->resolveEnumCondition($value);
                break;
                    
            case $value instanceof Like:
                $returnValue = $this->resolveLikeCondition($value);
                break;
                
            case $value instanceof ConditionJob:
                $returnValue = $this->resolveCondition($value);
                break;
                    
            case $value instanceof CaseData:
                $returnValue = $this->resolveFlowControlCase($value);
                break;
                    
            case $value instanceof FunctionJob:
                $returnValue = $this->resolveFunction($value);
                break;
        
            case $value instanceof Parenthesis:
                $returnValue = $this->resolveValue($value->getContain());
                break;
                    
            case $value instanceof Variable:
                $key = (string)$value;
                $parameters = $this->getStatementParameters();
                
                if ($key === '?') {
                    if (isset($parameters[$this->parameterCurrentIndex])) {
                        $returnValue = $parameters[$this->parameterCurrentIndex];
                        $this->parameterCurrentIndex++;
                    } else {
                        throw new Conflict("Too few arguments given!");
                    }
                    
                } else {
                    $returnValue = $parameters[$key];
                }
                break;
                    
            case $value instanceof ColumnSpecifier:
                $row = $this->getSourceRow();
                if (!isset($row[(string)$value])) {
                    return null;
                }
                $returnValue = $row[(string)$value];
                break;
                
            case $value instanceof SqlToken:
                $returnValue = $this->resolveSqlToken($value);
                break;
        
            case is_object($value):
                $type = get_class($value);
                throw new ErrorException("Cannot resolve object of type '{$type}'! (unimplemented!)");
                    
            case is_scalar($value):
                return $value;
        }
        
        if (is_scalar($returnValue) || is_null($returnValue)) {
            switch(gettype($returnValue)){
                
                case 'bool':
                    return $returnValue ?'TRUE' :'FALSE';
                
                case 'null':
                    return 'NULL';
                    
                default:
                    return (string)$returnValue;
            }
            
        } else {
            return $this->resolveValue($returnValue);
        }
    }
    
    public function resolveValueJob(Value $valueJob)
    {
        
        $chainValues = $valueJob->getChainValues();
        
        $value = null;
        foreach ($valueJob->getChainValues() as $chainValue) {
            if (is_null($value)) {
                $value = $chainValue;
                continue;
            }
            
            switch(true){
                case $chainValue instanceof Like:
                case $chainValue instanceof Enum:
                    $chainValue->setCheckValue($value);
                    break;
                    
                case $chainValue instanceof Condition:
                    $chainValue->setFirstParameter($value);
                    break;
            }
            
            $value = $this->resolveValue($chainValue);
            
        }
        
        return $this->resolveValue($value);
    }
    
    public function resolveCondition(ConditionJob $conditionJob)
    {
        
        $firstValue = $conditionJob->getFirstParameter();
        $lastValue  = $conditionJob->getLastParameter();
        
        $firstValue = $this->resolveValue($firstValue);
        $lastValue  = $this->resolveValue($lastValue);
        
        switch($conditionJob->getOperator()){
            case Operator::OP_ADDITION():
                return $firstValue + $lastValue;
                
            case Operator::OP_SUBTRACTION():
                return $firstValue - $lastValue;
                
            case Operator::OP_MULTIPLICATION():
                return $firstValue * $lastValue;
                
            case Operator::OP_DIVISION():
                return $firstValue / $lastValue;
                
            case Operator::OP_AND():
                return $firstValue && $lastValue;
                
            case Operator::OP_OR():
                return $firstValue || $lastValue;
                
            case Operator::OP_GREATER():
                return $firstValue > $lastValue;
                
            case Operator::OP_GREATEREQUAL():
                return $firstValue >= $lastValue;
                
            case Operator::OP_BETWEEN():
                return;
                
            case Operator::OP_NOT_BETWEEN():
                return;
                
            case Operator::OP_EQUAL():
                return $firstValue === $lastValue;
                
            case Operator::OP_NOT_EQUAL():
                return $firstValue !== $lastValue;
                
            case Operator::OP_EQUAL_NULLSAFE():
                return;
                
            case Operator::OP_LESSER():
                return $firstValue < $lastValue;
                
            case Operator::OP_LESSEREQUAL():
                return $firstValue <= $lastValue;
                
            case Operator::OP_LESSERGREATER():
                return true;
                
            case Operator::OP_IS():
                return $firstValue === $lastValue;
                
            case Operator::OP_IS_NOT():
                return $firstValue !== $lastValue;
                
            case Operator::OP_IS_NOT_NULL():
                return !is_null($firstValue);
                
            case Operator::OP_IS_NULL():
                return is_null($firstValue);
                
        }
    }
    
    public function resolveEnumCondition(Enum $enumJob)
    {
        
        $checkValue = $enumJob->getCheckValue();
        
        foreach ($enumJob->getValues() as $value) {
            $value = $this->resolveValue($value);
            
            if ($value === $checkValue) {
                return $enumJob->getIsNegated() ?false :true;
            }
        }
        
        return $enumJob->getIsNegated() ?true :false;
    }
    
    public function resolveLikeCondition(Like $likeJob)
    {
        
        $pattern = $likeJob->getPattern();
        
        $pattern = preg_replace("/[^a-zA-Z0-9]/is", "\\\$1", $pattern);
        
        $pattern = str_replace("%", ".*", $pattern);
        $pattern = str_replace("_", ".", $pattern);
        
        $result = preg_match("/{$pattern}/is", $likeJob->getCheckValue());
        
        return ($likeJob->getIsNegated() ?!$result :$result);
    }
    
    public function resolveFlowControlCase(CaseData $caseJob)
    {
        
        $checkValue = $caseJob->getCaseValue();
        
        foreach ($caseJob->getWhenThenStatements() as $values) {
            $whenValue = $this->resolveValue($values['when']);
            
            if ($whenValue === $checkValue) {
                return $this->resolveValue($values['then']);
            }
        }
        
        return $this->resolveValue($caseJob->getElseStatement());
    }
    
    public function resolveSqlToken(SqlToken $token)
    {
        
        switch($token){
            
            case SqlToken::T_DEFAULT():
                return $this->getCurrentColumnSchema()->getDefaultValue();
            
            case SqlToken::T_FALSE():
                return 0;
            
            case SqlToken::T_TRUE():
                return 1;
                
            case SqlToken::T_NULL():
                return null;
                        
            case SqlToken::T_CURRENT_TIMESTAMP():
                return date("Y-m-d H:i:s", time());
            
            case SqlToken::T_CURRENT_DATE():
                return date("Y-m-d", time());
            
            case SqlToken::T_CURRENT_TIME():
                return time();
            
            case SqlToken::T_CURRENT_USER():
                // There is simply no user management, so what to do here?
                return "NoUserManagementImplemented";
                
            default:
                throw new ErrorException("Unknown or unimplemented SqlToken '{$token->getName()}' to resolve to scalar value!");
            
        }
    }
    
    public function resolveFunction(FunctionJob $functionJob)
    {
        
        $functionName = $functionJob->getName();
        
        $classNameFunctionPart = str_replace("_", " ", strtolower($functionName));
        $classNameFunctionPart = ucwords($classNameFunctionPart);
        $classNameFunctionPart = str_replace(" ", "", $classNameFunctionPart);
        
        $className = "\Addiks\PHPSQL\{$classNameFunctionPart}";
        
        if (!class_exists($className)) {
            throw new ErrorException("Unknown or unimplemented function '{$functionName}' called! (No class '{$className}' found!)");
        }
        
        /* @var $functionExecuter FunctionResolver */
        $functionExecuter = $this->factory($className);
        
        $functionExecuter->setValueResolver($this);
        
        $functionArguments = array();
        foreach ($functionJob->getArguments() as $argument) {
            if ($argument instanceof Parameter) {
                $value = $argument->getValue();
            } else {
                $value = $argument;
            }
            
            $value = $this->resolveValue($value);
            
            $functionArguments[] = $value;
        }
        
        $actualArgumentCount = count($functionArguments);
        if ($actualArgumentCount !== $functionExecuter->getExpectedParameterCount()) {
            $errorMessage  = "Invalid argument count of {$actualArgumentCount} for function '{$functionName}', ";
            $errorMessage .= "expected {$functionExecuter->getExpectedParameterCount()} arguments!";
            throw new InvalidArgument($errorMessage);
        }
        
        if ($functionExecuter instanceof AggregateInterface) {
            /* @var $resultSet SelectResult */
            $resultSet = $this->getResultSet();
            
            if (!$resultSet instanceof SelectResult) {
                throw new Conflict("Cannot use aggregate-function '{$functionName}' without select-result!");
            }
            
            if (count($resultSet->getStatement()->getGroupings())<=0) {
                throw new Conflict("Cannot use aggregate-function '{$functionName}' without GROUP BY in statement!");
            }
            
            $rowIds = $resultSet->getCurrentGroupedRowIds();
            
            $functionExecuter->setResultSet($resultSet);
            $functionExecuter->setRowIdsInCurrentGroup($rowIds);
            
        }
        
        return $functionExecuter->executeFunction($functionJob, $functionArguments);
    }
}

<?php

namespace Addiks\PHPSQL\ValueResolver\FunctionResolver;

use Addiks\PHPSQL\ValueResolver\FunctionResolver;
use Addiks\PHPSQL\Entity\Result\ResultInterface;
use Addiks\PHPSQL\Entity\Job\Part\FunctionJob;
use Addiks\PHPSQL\Entity\ExecutionContext;

class SumFunction implements FunctionResolverInterface, AggregateInterface
{
    public function __construct(ValueResolver $valueResolver)
    {
        $this->valueResolver = $valueResolver;
    }

    private $valueResolver;

    private $rowIdsInGrouping = array();
    
    public function setRowIdsInCurrentGroup(array $rowIds)
    {
        $this->rowIdsInGrouping = $rowIds;
    }
    
    private $resultSet;
    
    public function setResultSet(ResultInterface $result)
    {
        $this->resultSet = $result;
    }
    
    public function executeFunction(
        FunctionJob $function,
        ExecutionContext $context
    ) {
        
        /* @var $result SelectResult */
        $result = $this->resultSet;
        
        /* @var $argumentValue Value */
        $argumentValue = current($function->getArguments());
        
        $sum = 0;
        
        foreach ($this->rowIdsInGrouping as $rowId) {
            $row = $result->getRowUnresolved($rowId);
            
            $this->valueResolver->setSourceRow($row);
            
            $value = $this->valueResolver->resolveValue($argumentValue);
            
            if (is_numeric($value)) {
                $sum += $value;
            }
        }
        
        return $sum;
    }
}

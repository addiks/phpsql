<?php

namespace Addiks\PHPSQL\ValueResolver\FunctionResolver;

use Addiks\PHPSQL\ValueResolver\FunctionResolver;
use Addiks\PHPSQL\Result\ResultInterface;
use Addiks\PHPSQL\Job\Part\FunctionJob;
use Addiks\PHPSQL\StatementExecutor\ExecutionContext;

class CountFunction implements FunctionInterface
{
    public function __construct(ValueResolver $valueResolver)
    {
        $this->valueResolver = $valueResolver;
    }

    private $valueResolver;

    public function executeFunction(
        FunctionJob $function,
        ExecutionContext $context
    ) {
        /* @var $result SelectResult */
        $result = $this->resultSet;
        
        /* @var $argumentValue Value */
        $argumentValue = current($function->getArguments());
        
        $count = 0;

        $beforeSourceRow = $context->getCurrentSourceRow();
        
        foreach ($context->getCurrentSourceSet() as $row) {
            $context->setCurrentSourceRow($row);
            
            $value = $this->valueResolver->resolveValue($argumentValue);
            
            if (!is_null($value)) {
                $count++;
            }
        }

        $context->setCurrentSourceRow($beforeSourceRow);
        
        return $count;
    }
}

<?php

namespace Addiks\PHPSQL\ValueResolver\FunctionResolver;

use Addiks\PHPSQL\ValueResolver\FunctionResolver;
use Addiks\PHPSQL\Entity\Result\ResultInterface;
use Addiks\PHPSQL\Entity\Job\Part\FunctionJob;
use Addiks\PHPSQL\Entity\ExecutionContext;
use Addiks\PHPSQL\ValueResolver;

class SumFunction implements FunctionResolverInterface
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
        /* @var $argumentValue Value */
        $argumentValue = current($function->getArguments());
        
        $beforeSourceRow = $context->getCurrentSourceRow();
        
        $sum = 0;
        foreach ($context->getCurrentSourceSet() as $row) {
            $context->setCurrentSourceRow($row);
            
            $value = $this->valueResolver->resolveValue($argumentValue, $context);
            
            if (is_numeric($value)) {
                $sum += $value;
            }
        }

        $context->setCurrentSourceRow($beforeSourceRow);
        
        return $sum;
    }
}

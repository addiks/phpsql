<?php

namespace Addiks\PHPSQL\ValueResolver\FunctionResolver;

use Addiks\PHPSQL\ValueResolver\FunctionResolver;
use Addiks\PHPSQL\Entity\Job\Part\FunctionJob;
use Addiks\PHPSQL\Entity\ExecutionContext;

class RandFunction implements FunctionResolverInterface
{
    public function __construct(ValueResolver $valueResolver)
    {
    }

    public function executeFunction(
        FunctionJob $function,
        ExecutionContext $context
    ) {
        
        return rand(0, 100000) / 100000;
    }
}

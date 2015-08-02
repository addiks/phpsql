<?php

namespace Addiks\PHPSQL\ValueResolver\FunctionResolver;

use Addiks\PHPSQL\ValueResolver\FunctionResolver;
use Addiks\PHPSQL\Entity\Job\FunctionJob;

class RandFunction implements FunctionResolverInterface
{
    
    public function getExpectedParameterCount()
    {
        return 0;
    }
    
    public function executeFunction(FunctionJob $functionJob, array $functionArguments = array())
    {
        
        return rand(0, 100000) / 100000;
    }
}

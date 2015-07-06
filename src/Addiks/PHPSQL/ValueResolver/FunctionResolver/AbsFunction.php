<?php

namespace Addiks\PHPSQL\ValueResolver\FunctionResolver;

use Addiks\PHPSQL\ValueResolver\FunctionResolver;
use Addiks\PHPSQL\Entity\Job\FunctionJob;

class AbsFunction extends FunctionResolver
{
    
    public function getExpectedParameterCount()
    {
        return 1;
    }
    
    public function executeFunction(FunctionJob $function, array $args = array())
    {
        
        return abs($args[0]);
    }
}

<?php

namespace Addiks\PHPSQL\Service\ValueResolver\FunctionResolver;

use Addiks\PHPSQL\Service\ValueResolver\FunctionResolver;
use Addiks\PHPSQL\Entity\Job\FunctionJob;

class RandFunction extends FunctionResolver
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

<?php

namespace Addiks\PHPSQL\ValueResolver\FunctionResolver;

use DateTime;
use Addiks\PHPSQL\ValueResolver\FunctionResolver;
use Addiks\PHPSQL\Entity\Job\Part\FunctionJob;
use Addiks\PHPSQL\Entity\ExecutionContext;
use Addiks\PHPSQL\ValueResolver;

class NowFunction implements FunctionResolverInterface
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
        return (new DateTime("now"))->format("Y-m-d H:i:s");
    }
}

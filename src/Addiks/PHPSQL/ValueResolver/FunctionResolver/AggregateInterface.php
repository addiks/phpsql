<?php

namespace Addiks\PHPSQL\ValueResolver\FunctionResolver;

use Addiks\PHPSQL\Entity\Result\ResultInterface;

interface AggregateInterface
{
    
    function setRowIdsInCurrentGroup(array $rowIds);

    function setResultSet(ResultInterface $result);
}

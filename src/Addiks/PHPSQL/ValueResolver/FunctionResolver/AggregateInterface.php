<?php

namespace Addiks\PHPSQL\ValueResolver\FunctionResolver;

use Addiks\PHPSQL\Entity\Result\ResultInterface;

interface AggregateInterface
{
    public function setRowIdsInCurrentGroup(array $rowIds);

    public function setResultSet(ResultInterface $result);
}

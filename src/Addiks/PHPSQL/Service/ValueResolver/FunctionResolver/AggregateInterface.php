<?php 

namespace Addiks\Database\Service\ValueResolver\FunctionResolver;

use Addiks\Database\Entity\Result\ResultInterface;
interface AggregateInterface{
	
	function setRowIdsInCurrentGroup(array $rowIds);

	function setResultSet(ResultInterface $result);
	
}
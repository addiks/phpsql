<?php 

namespace Addiks\Database\Service\ValueResolver\FunctionResolver;

use Addiks\Database\Service\ValueResolver\FunctionResolver;
use Addiks\Database\Entity\Job\FunctionJob;

class RandFunction extends FunctionResolver{
	
	public function getExpectedParameterCount(){
		return 0;
	}
	
	public function executeFunction(FunctionJob $functionJob, array $functionArguments = array()){
		
		return rand(0, 100000) / 100000;
	}
	
}
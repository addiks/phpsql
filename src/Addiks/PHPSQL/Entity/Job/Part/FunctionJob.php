<?php
/**
 * Copyright (C) 2013  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.    
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 * @package Addiks
 */

namespace Addiks\Database\Entity\Job\Part;

use Addiks\Database\Entity\Job\Part;

class FunctionJob extends Part{
	
	private $name;
	
	public function setName($name){
		$this->name = $name;
	}
	
	public function getName(){
		return $this->name;
	}
	
	private $arguments = array();
	
	public function addArgumentValue($value){
		$this->arguments[] = $value;
	}
	
	public function getArguments(){
		return $this->arguments;
	}
	
	private $parameters = array();
	
	public function addParameter(Parameter $parameter){
		$this->parameters[] = $parameter;
	}
	
	public function getParameters(){
		return $this->parameters;
	}
}
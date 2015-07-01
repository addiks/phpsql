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

class Value extends Part{
	
	private $chain = array();
	
	public function addChainValue($value){
		$this->chain[] = $value;
	}
	
	public function getChainValues(){
		return $this->chain;
	}
	
	public function generateAlias(){
		
		if(is_null($this->getAlias())){
			$this->setAlias("");
			
			foreach($this->chain as $chainAlias => $chainValue){
				
				switch(true){
			
					case $chainValue instanceof Column:
						$this->setAlias($this->getAlias() . (string)$chainValue);
						break;
						
					case $chainValue instanceof FunctionJob:
						$this->setAlias($this->getAlias() . $chainValue->getName() . "()");
						break;
			
					case method_exists($chainValue, 'getAlias'):
						$this->setAlias($this->getAlias() . $chainValue->getAlias());
						break;
				}
			}
			
			if(strlen(parent::getAlias())<=0){
				$this->setAlias($this->getAlias() . "VALUE#".uniqid());
			}
			
		}
		return $this->getAlias();
	}
	
}
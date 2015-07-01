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

namespace Addiks\Database\Entity\Job\Statement\Create;

use Addiks\Database\Entity\Job\Statement\CreateStatement;

use Addiks\Database\Entity\Job\Statement\Create;
use Addiks\Database\Service\Executor\CreateDatabaseExecutor;

/**
 * 
 * @author gerrit
 * @Addiks\Statement(executorClass="CreateDatabaseExecutor")
 *
 */
class CreateDatabaseStatement extends CreateStatement{
	
	private $characterSet;
	
	public function setCharacterSet($characterSet){
		$this->characterSet = $characterSet;
	}
	
	public function getCharacterSet(){
		return $this->characterSet;
	}
	
	private $collation;
	
	public function setCollation($collation){
		$this->collation = $collation;
	}
	
	public function getCollation(){
		return $this->collation;
	}
	
}
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

namespace Addiks\Database\Value\Database\Dsn;

use Addiks\Common\Entity\Exception\InvalidValue;

use Addiks\Database\Value\Database\Dsn;

use Addiks\Database\Resource\Database;

class Internal extends Dsn{
	
	const PATTERN = "^[a-z0-9_]+$";
	
	const DRIVERNAME = "internal";
	
	private $databaseId = Database::DATABASE_ID_DEFAULT;
	
	static protected function filter($value){
		$value = parent::filter($value);
		
		if(strlen($value)<=0){
			return "internal:default";
			
		}else{
			return $value;
		}
	}
	
	public function validate($value){
		
		parent::validate($value);
		
		$pattern = self::PATTERN;
		
		$parts = explode(":", $value);
		
		if(count($parts)<2 || $parts[0]!==self::DRIVERNAME || !preg_match("/{$pattern}/is", $parts[1])){
			throw new InvalidValue("Invalid DSN for internal database: '{$value}'");
		}
	}
	
	public function getDatabaseId(){
		
		$parts = explode(":", $this->getValue());
		
		return (int)$parts[1];
	}
}
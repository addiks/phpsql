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

class MySQL extends Dsn{
	
	/**
	 * gets the database-name.
	 * @return string
	 */
	public function getDatabaseName(){
		return $this->getAsAssociative()['dbname'];
	}
	
	/**
	 * gets the database-hostname.
	 * @return string
	 */
	public function getHostname(){
		return $this->getAsAssociative()['host'];
	}
	
	/**
	 * Gets the parts of the DSN as an associative array.
	 * @see Value::getValue()
	 * @return array
	 */
	protected function getAsAssociative(){
	
		$value = $this->getValue();
	
		$value = explode(":", $value);
		$value = $value[1];
	
		$value = explode(";", $value);
	
		$values = array();
		foreach($value as $value){
			$value = explode("=", $value);
				
			list($key, $value) = $value;
	
			$values[$key] = $value;
		}
	
		return $values;
	}
}
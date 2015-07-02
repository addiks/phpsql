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

namespace Addiks\PHPSQL\Value\Text;

use Addiks\PHPSQL\Value\Text;

/**
 * Class representing a hash.
 * @see http://en.wikipedia.org/wiki/function
 */
class Hash extends Text{
	
	static public function filter($value){
		return strtolower(trim(parent::filter($value)));
	}
	
	public function validate($value){
		
		parent::validate($value);
		
		$allowedChars = [
			'a', 'b', 'c', 'd',
			'e', 'f', '0', '1',
			'2', '3', '4', '5',
			'6', '7', '8', '9',
		];
		
		if(strlen($value) !== 32 || str_replace($allowedChars, '', $value) !== ''){
			throw new  Exception\InvalidValue("Not a valid hash: '{$value}'!");
		}
	}
	
	static public function createRandom(){
		return static::factory(md5(microtime() . rand(0, pow(2, 16)) . mt_rand(0, pow(2, 16))));
	}
	
}
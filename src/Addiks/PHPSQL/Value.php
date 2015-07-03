<?php
/**
 * Copyright (C) 2013  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.    
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL;

use ErrorException;
use InvalidArgumentException;

/**
 * A value-object ("VO") is a object holding or representing specific data.
 *
 * <p>
 * Value-Objects does not have an own identification.
 * That means that each deterministic scalar value is represented by only one value-object per runtime.
 * Also means that you can never change the value of a value-object, only create new VO's from new values.
 * The static factory method will for the same value (and class) return the same value object,
 * that makes it possible to compare value objects from different source for equal identity:
 *
 *  static::factory("A") === static::factory("A")
 *
 * Concrete Value-Objects can provide data-specific value-logic and validation.
 * For example, a Date-VO can only represent a valid date.
 * </p>
 *
 * <p>
 * Consider that value-objects do not communicate with the external world, that means that
 * a file-value-object would NEVER check if it's file does actualy exist!
 *
 * Such a check (if a value-object is valid against the outer world) would
 * always be done by 'Service'- or 'Resource'-object's.
 * </p>
 * 
 * @see http://c2.com/cgi/wiki?ValueObject
 */
abstract class Value{
	
	/**
	 * @param scalar $value
	 * @throws InvalidArgumentException
	 */
	private function __construct($value){
		
		$this->validate($value);
		
		$this->value = $value;
	}
	
	static private $instances = array();
	
	/**
	 * Creates a new value object.
	 * 
	 * If the value is invalid, an invalid-argument-exception is thrown.
	 * @see self::validate() 
	 *
	 * @param scalar $value
	 * @throws InvalidArgumentException
	 */
	static public function factory($value){
		
		if($value instanceof self){
			$value = $value->getValue();
		}
		
		$value = static::filter($value);
		
		if(!is_scalar($value)){
			throw new ErrorException("A value object must be created from a scalar value!");
		}
		
		$classname = get_called_class();
		
		if(!isset(self::$instances[$classname])){
			self::$instances[$classname] = array();
		}
	
		// lazy load the value-object
		if(!isset(self::$instances[$classname][$value])){
			
			if(func_num_args()>1){
				// construct value-object with more then the pure scalar value
				// (not really correct in terms of value-objects, but sometimes needed)
				$arguments = func_get_args();
				$reflection = new \ReflectionClass($classname);
				self::$instances[$classname][$value] = $reflection->newInstanceArgs($arguments);
				
			}else{
				self::$instances[$classname][$value] = new static($value);
			}
		}
	
		return self::$instances[$classname][$value];
	}
	
	/**
	 * Gets the simplde (scalar) data-type that would fit this VO best.
	 * @return string
	 * @see gettype()
	 */
	static public function getSimpleDataType(){
		return "string";
	}
	
	/** @var scalar */
	private $value;
	
	/**
	 * Gets the scalar value of this VO.
	 * @return scalar
	 */
	public function getValue(){
		return $this->value;
	}
	
	/**
	 * Magic cast to string.
	 * Alias of static::getValue()
	 * 
	 * @see self::getValue()
	 * @return string
	 */
	public function __toString(){
		return (string)$this->getValue();
	}
	
	/**
	 * Validates the value before set.
	 * This will be overwritten/extended by concrete 
	 * value-classes with additional checks.
	 * 
	 * @param scalar $value
	 * @throws InvalidArgumentException
	 */
	protected function validate($value){
		
		if(!is_scalar($value)){
			throw new InvalidArgumentException("Value needs to be scalar!");
		}
	}
	
	/**
	 * Applies a filter to the value before validating it.
	 * 
	 * @param scalar $value
	 * @return scalar
	 */
	static protected function filter($value){
		return $value;
	}
	
}

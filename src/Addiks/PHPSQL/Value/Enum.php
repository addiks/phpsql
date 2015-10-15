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

namespace Addiks\PHPSQL\Value;

use ErrorException;
use Addiks\PHPSQL\Value\Value;
use ReflectionClass;

/**
 * enum helper, usage:
 *
 * class FooStatus extends Enum{const BAR=1;const BAZ=2;}
 *
 * function blah(FooStatus $stat){
 *   switch($stat->getValue()){
 *     case FooStatus::BAR: ...
 *
 * @author Gerrit Addiks <gerrit@addiks.de>
 * @package Addiks
 */
abstract class Enum extends Value
{
    
    /** constant name of this enum-value-object @var string */
    private $const_name;
    
    /**
     * Gets constant name of this enum-value-object.
     * @return string
     */
    public function getName()
    {
        return $this->const_name;
    }
    
    /**
     * constructor.
     * @param string $name
     */
    protected function __construct($value)
    {
        $classname = get_class($this);
        
        if (defined("{$classname}::{$value}")) {
            $this->const_name = $value;
            return;
        }
        
        $key = static::getByValue($value);

        if (is_null($key)) {
            $reflection = new ReflectionClass(get_called_class());
        
            foreach (array_keys($reflection->getConstants()) as $name) {
                if (strtolower($name) === strtolower($value)) {
                    $key = $name;
                }
            }
        
        }
        
        if (is_null($key)) {
            throw new ErrorException("Value '{$value}' does not match enumeration '{$classname}'");
        } elseif (!defined("{$classname}::{$key}")) {
            throw new ErrorException("Key '{$key}' does not match enumeration '{$classname}'");
        }
        
        $this->const_name = $key;
    }
    
    /**
     * static magic caller
     * @param string $name
     * @param array $args
     * @return mixed
     */
    public static function __callStatic($name, $args)
    {
        return static::factory($name);
    }
    
    /**
     * Array with all instances.
     * When an enum-value is requested that already exists
     * somewhere else, that one is returned instead.
     *
     * That allows the developper to simple compare objects:
     *
     * if(Value\Enum\Foo::BAR() === $maybeBarEnumValue){
     *      # $maybeBarEnumValue is an foo-enum with value 'bar'
     * }
     *
     * array(
     *  [CLASSNAME] => array(
     *      [KEY] => Enum\...
     *  )
     * )
     *
     * @var array
     */
    static private $instances = array();
    
    /**
     * Creates new enum-objects.
     * @param string $name
     */
    public static function factory($name)
    {
        $classname = get_called_class();
        
        if (!isset(self::$instances[$classname])) {
            self::$instances[$classname] = array();
        }
        
        if ($name instanceof Value) {
            $name = $name->getValue();
        }
        
        if (!isset(self::$instances[$classname][$name])) {
            $instance = new static($name);
            $name = $instance->getName();
            
            if (!isset(self::$instances[$classname][$name])) {
                self::$instances[$classname][$name] = $instance;
            }
        }
        
        return self::$instances[$classname][$name];
    }
    
    /**
     * gets the value of this value-constant-object
     * @see self::getValue
     * @return mixed
     */
    public function getValue()
    {
        $key = $this->const_name;
        $classname = get_class($this);
        
        return constant("{$classname}::{$key}");
    }
    
    /**
     * alias of self::getValue
     * @see self::getValue
     * @return mixed
     */
    public function getVal()
    {
        return $this->getVal();
    }
    
    /**
     * magic string cast.
     * @return string
     */
    public function __toString()
    {
        return (string)$this->getValue();
    }
    
    /**
     * Gets name of enum-entry by searching for specific value.
     * @param mixed $value
     * @return string
     */
    public static function getByValue($needle)
    {
        $classname = get_called_class();
        $reflection = new ReflectionClass($classname);
        
        foreach ($reflection->getConstants() as $name => $value) {
            if ($value === $needle) {
                return static::factory($name);
            }
        }
        
        throw new ErrorException("Value '{$needle}' does not match enumeration '{$classname}'");
    }
}

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

namespace Addiks\PHPSQL\Iterators;

use ArrayAccess;
use IteratorIterator;
use Addiks\PHPSQL\Iterators\UsesBinaryDataInterface;

/**
 * Iterator object where every action can be controlled by lambda-functions.
 *
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 * !!!      DEPRECATED: Please do not use this iterator anymore for new code.       !!!
 * !!!      I want to get rid of it, it produces more problems than it's worth      !!!
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 *
 * @deprecated
 *
 * Example:
 *
 *  $items = array("asd", "fasel", "bar", "blah");
 *
 *  $iterator = new CustomIterator(new ArrayIterator($items), array(
 *      'current' => function($value){
 *          return "foo-{$value}-baz ";
 *      },
 *      'next' => function() use (&$items){
 *          next($items);
 *          while(strlen(current($items))>4){
 *              next($items); // skip items loger then 4 chars
 *          }
 *      }
 *  ));
 *
 *  foreach($iterator as $item){
 *      echo $item
 *  }
 *
 *  # OUTPUT: # foo-asd-baz foo-bar-baz foo-blah-baz
 *
 * @see \IteratorIterator
 * @author Gerrit Addiks <gerrit@addiks.de>
 * @package Addiks
 */
class CustomIterator extends IteratorIterator implements ArrayAccess, UsesBinaryDataInterface
{

    /**
     * constructor.
     * @param array $array
     * @param array $lambdas array(\Closure)
     */
    public function __construct($iterator = null, $lambdas = array())
    {

        if (is_null($iterator)) {
            $iterator = new \ArrayIterator(array());
        } elseif (is_array($iterator)) {
            $iterator = new \ArrayIterator($iterator);
        }

        parent::__construct($iterator);

        foreach ($lambdas as $key => $lambda) {
            switch($key){
                case 'rewind':  $this->setRewindCallback($lambda);
                    break;
                case 'valid':   $this->setValidCallback($lambda);
                    break;
                case 'current': $this->setCurrentCallback($lambda);
                    break;
                case 'key':     $this->setKeyCallback($lambda);
                    break;
                case 'next':    $this->setNextCallback($lambda);
                    break;
                case 'seek':    $this->setSeekCallback($lambda);
                    break;
                case 'set':     $this->setSeekCallback($lambda);
                    break;
                case 'get':     $this->setGetCallback($lambda);
                    break;
                case 'isset':   $this->setIssetCallback($lambda);
                    break;
                case 'unset':   $this->setUnsetCallback($lambda);
                    break;
            }
        }

        // for debugging, can consume high memory
        if (false) {
            $this->constructorTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        }
    }

    private $constructorTrace = array();

    public function getConstructorTrace()
    {
        return $this->constructorTrace;
    }

    public function __call($name, $arguments)
    {

        if (is_null($this->getInnerIterator())) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
            $methodString = __CLASS__."::{$name}()";
            $positionString = "in {$trace['file']} on line {$trace['line']}";
            throw new ErrorException("Call to undefined method {$methodString} {$positionString}");
        }

        return call_user_func_array([$this->getInnerIterator(), $name], $arguments);
    }

    ### CURRENT ###

    /** @var \Closure */
    private $currentCallback;

    /**
     * sets callback for current
     * @see self::current
     * @param \Closure $lambda
     */
    public function setCurrentCallback(\Closure $lambda)
    {
        $this->currentCallback = $lambda;
    }

    /**
     * gets callback for current
     * @see self::current
     * @return \Closure
     */
    public function getCurrentCallback()
    {
        return $this->currentCallback;
    }

    /**
     * gets current value
     * @return mixed
     * @see ArrayIterator::current()
     */
    public function current()
    {
        if (is_callable($this->currentCallback)) {
            $return = call_user_func($this->currentCallback, parent::current());
            return $return;
        } else {
            return parent::current();
        }
    }

    ### KEY ###

    /** @var \Closure */
    private $keyCallback;

    /**
     * sets callback for key
     * @see self::key
     * @param \Closure $lambda
     */
    public function setKeyCallback(\Closure $lambda)
    {
        $this->keyCallback = $lambda;
    }

    /**
     * gets callback for key
     * @see self::key
     * @return \Closure
     */
    public function getKeyCallback()
    {
        return $this->keyCallback;
    }

    /**
     * @return mixed
     * @see ArrayIterator::key()
     */
    public function key()
    {
        if (is_callable($this->keyCallback)) {
            return call_user_func($this->keyCallback, parent::key());
        } else {
            return parent::key();
        }
    }

    ### NEXT ###

    /** @var \Closure */
    private $nextCallback;

    /**
     * sets callback for next
     * @see self::next
     * @param \Closure $lambda
     */
    public function setNextCallback(\Closure $lambda)
    {
        $this->nextCallback = $lambda;
    }

    /**
     * gets callback for next
     * @see self::next
     * @return \Closure
     */
    public function getNextCallback()
    {
        return $this->nextCallback;
    }

    /**
     * @return mixed
     * @see ArrayIterator::next()
     */
    public function next()
    {
        if (is_callable($this->nextCallback)) {
            return call_user_func($this->nextCallback, function () {
                parent::next();

            });
        } else {
            return parent::next();
        }
    }

    ### VALID ###

    /** @var \Closure */
    private $validCallback;

    /**
     * sets callback for valid
     * @see self::valid
     * @param \Closure $lambda
     */
    public function setValidCallback(\Closure $lambda)
    {
        $this->validCallback = $lambda;
    }

    /**
     * gets callback for valid
     * @see self::valid
     * @return \Closure
     */
    public function getValidCallback()
    {
        return $this->validCallback;
    }

    /**
     * @return mixed
     * @see ArrayIterator::valid()
     */
    public function valid()
    {
        if (is_callable($this->validCallback)) {
            return call_user_func($this->validCallback, parent::valid());
        } else {
            return parent::valid();
        }
    }

    ### REWIND ###

    /** @var \Closure */
    private $rewindCallback;

    /**
     * sets callback for rewind
     * @see self::rewind
     * @param \Closure $lambda
     */
    public function setRewindCallback(\Closure $lambda)
    {
        $this->rewindCallback = $lambda;
    }

    /**
     * gets callback for rewind
     * @see self::rewind
     * @return \Closure
     */
    public function getRewindCallback()
    {
        return $this->rewindCallback;
    }

    /**
     * @return mixed
     * @see ArrayIterator::rewind()
     */
    public function rewind()
    {
        if (is_callable($this->rewindCallback)) {
            return call_user_func($this->rewindCallback, function () {
                parent::rewind();

            });
        } else {
            return parent::rewind();
        }
    }

    ### SEEK ###

    /**
     * @var \Closure
     */
    private $seekCallback;

    public function setSeekCallback(\Closure $closure)
    {
        $this->seekCallback = $closure;
    }

    public function getSeekCallback()
    {
        return $this->seekCallback;
    }

    public function seek($rowId)
    {
        if (is_callable($this->seekCallback)) {
            $seekClosure = function () use ($rowId) {
                parent::seek($rowId);

            };
            return call_user_func($this->seekCallback, $seekClosure, $rowId);
        } else {
            return $this->getInnerIterator()->seek($rowId);
        }
    }

    ### SET ###

    /**
     * @var \Closure
     */
    private $setCallback;

    public function setSetCallback(\Closure $callback)
    {
        $this->setCallback = $callback;
    }

    public function getSetCallback()
    {
        return $this->setCallback;
    }

    public function __set($key, $value)
    {
        if (is_callable($this->setCallback)) {
            return call_user_func($this->setCallback, $key, $value);
        } else {
            return $this->getInnerIterator()->$key = $value;
        }
    }

    public function offsetSet($offset, $value)
    {
        $this->__set($offset, $value);
    }

    ### GET ###

    /**
     * @var \Closure
     */
    private $getCallback;

    public function setGetCallback(\Closure $callback)
    {
        $this->getCallback = $callback;
    }

    public function getGetCallback()
    {
        return $this->getCallback;
    }

    public function __get($key)
    {
        if (is_callable($this->getCallback)) {
            return call_user_func($this->getCallback, $key);
        } else {
            return $this->getInnerIterator()->$key;
        }
    }

    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }

    ### ISSET ###

    /**
     * @var \Closure
     */
    private $issetCallback;

    public function setIssetCallback(\Closure $callback)
    {
        $this->issetCallback = $callback;
    }

    public function getIssetCallback()
    {
        return $this->issetCallback;
    }

    public function __isset($key)
    {
        if (is_callable($this->issetCallback)) {
            return call_user_func($this->issetCallback, $key);
        } else {
            return isset($this->getInnerIterator()->$key);
        }
    }

    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }

    ### UNSET ###

    /**
     * @var \Closure
     */
    private $unsetCallback;

    public function setUnsetCallback(\Closure $callback)
    {
        $this->unsetCallback = $callback;
    }

    public function getUnsetCallback()
    {
        return $this->unsetCallback;
    }

    public function __unset($key)
    {
        if (is_callable($this->unsetCallback)) {
            return call_user_func($this->unsetCallback, $key);
        } else {
            unset($this->getInnerIterator()->$key);
        }
    }

    public function offsetUnset($offset)
    {
        return $this->__unset($offset);
    }

    ### BINARY INTERFACE

    public function usesBinaryData()
    {
        $isBinary = false;
        if ($this->getInnerIterator() instanceof UsesBinaryDataInterface) {
            $isBinary = $this->getInnerIterator()->usesBinaryData();
        }
        return $isBinary;
    }

    public function convertDataRowToStringRow(array $row)
    {
        if ($this->getInnerIterator() instanceof UsesBinaryDataInterface) {
            $row = $this->getInnerIterator()->convertDataRowToStringRow($row);
        }
        return $row;
    }

    public function convertStringRowToDataRow(array $row)
    {
        if ($this->getInnerIterator() instanceof UsesBinaryDataInterface) {
            $row = $this->getInnerIterator()->convertStringRowToDataRow($row);
        }
        return $row;
    }

}

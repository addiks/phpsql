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

use ErrorException;
use Addiks\PHPSQL\Value\Text;
use ArrayAccess;

class Annotation extends Text implements ArrayAccess{
	
	static protected function filter($value){
		return trim(parent::filter($value));
	}
	
	protected function validate($value){
		if($value[0]!=='@'){
			throw new ErrorException("Annotations have to begin with '@'!");
		}
	}
	
	public function getNamespace(){
		return $this->parseData()['namespace'];
	}
	
	public function getName(){
		return $this->parseData()['name'];
	}
	
	public function getIdentifier(){
		$namespace = $this->getNamespace();
		if(strlen($namespace)>0){
			return "{$namespace}\\{$this->getName()}";
		}else{
			return $this->getName();
		}
	}
	
	public function getAttributes(){
		return $this->parseData()['attributes'];
	}
	
	public function getTags(){
		return $this->parseData()['tags'];
	}
	
	private $dataCache;
	
	/**
	 * Parses the annotation-line down to its components.
	 * 
	 * array(
	 * 	'namespace' => $namespace,
	 *  'name'      => $name,
	 *  'attributes' => array(
	 *  	'someKey' => 'someValue',
	 *  	'otherKey' => 'otherValue',
	 *  ),
	 *  'tags' => array(
	 *  	'foo',
	 *  	'bar',
	 *  	'baz'
	 *  )
	 * )
	 * 
	 * @return array
	 */
	protected function parseData(){
		if(is_null($this->dataCache)){
			$patternNamespace  = '(?P<namespace>[a-zA-Z0-9\\\\]+\\\\)?';
			$patternName       = '(?P<name>[a-zA-Z0-9]+)';
			$patternString     = '\"[^\"]*\"|\'[^\']*\'';
			$patternFloat      = '[0-9]+\.[0-9]+';
			$patternInt        = '[0-9]+';
			$patternBool       = 'true|false';
			$patternAttrValues = "({$patternString}|{$patternFloat}|{$patternInt}|{$patternBool})";
			$patternAttributes = "(?P<attr>\(\s*([a-zA-Z0-9]+\={$patternAttrValues}\s*\,?\s*)+\s*\))?";
			$pattern           = "^\@{$patternNamespace}{$patternName}\s*{$patternAttributes}(?P<rawtext>.*)$";
			
			if(preg_match("/{$pattern}/is", $this->getValue(), $matches)){
				
				$this->dataCache = array(
					'namespace'   => $matches['namespace'],
					'name'        => $matches['name'],
					'attributes'  => array(),	
					'tags'        => array(),	
				);
				
				if(preg_match_all("/(?P<key>[a-zA-Z0-9]+)\s*\=\s*(?P<value>{$patternAttrValues})/is", $matches['attr'], $attributeMatches, PREG_SET_ORDER)){
					
					foreach($attributeMatches as $attributeMatch){
						$key   = $attributeMatch['key'];
						$value = $attributeMatch['value'];
						switch(true){
							case $value === 'true':
								$value = true;
								break;
								
							case $value === 'false':
								$value = false;
								break;
								
							case $value[0] === '"':
							case $value[0] === "'":
								$value = substr($value, 1, strlen($value)-2);
								break;
								
							case strpos($value, '.')>0:
								$value = (float)$value;
								break;
								
							case is_numeric($value):
								$value = (int)$value;
								break;
								
						}
						$this->dataCache['attributes'][$key] = $value;
					}
				}
				
				foreach(preg_split("/\s+/is", trim($matches['rawtext'])) as $attributeTag){
					$this->dataCache['tags'][] = $attributeTag;
				}
			}
		}
		return $this->dataCache;
	}
	
	### ARRAY ACCESS
	
	public function offsetGet($offset){
		if(is_numeric($offset)){
			if(isset($this->getTags()[$offset])){
				return $this->getTags()[$offset];
			}
		}else{
			$attributes = $this->getAttributes();
			if(isset($attributes[$offset])){
				return $attributes[$offset];
			}
		}
	}
	
	public function offsetExists($offset){
		if(is_numeric($offset)){
			return isset($this->getTags()[$offset]);
		}else{
			$attributes = $this->getAttributes();
			return isset($attributes[$offset]);
		}
	}
	
	public function offsetSet($offset, $value){
	}
	
	public function offsetUnset($offset){
	}
}

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

namespace Addiks\Database\Entity\Page;

use Addiks\Database\Value\Enum\Page\Schema\InsertMethod;
use Addiks\Database\Value\Enum\Page\Schema\RowFormat;
use Addiks\Database\Value\Enum\Page\Schema\Engine;
use Addiks\Database\Value\Enum\Page\Schema\Type;

use Addiks\Common\Entity;

/**
 * A page in the database index containing information about tables, views, ... in the database.
 * 
 */
class Schema extends Entity{
	
	/**
	 * name:          64 byte
	 * engine:         2 byte
	 * collation:      8 byte
	 * checksum:	   1 byte
	 * maxRows:        8 byte
	 * minRows:        8 byte
	 * packKeys:       1 byte
	 * delayKeyWrite:  1 byte
	 * rowFormat:      2 byte
	 * insertMethod:   2 byte
	 *              _________
	 * used data:     97 byte
	 * reserved:      31 byte
	 *              _________
	 * page-size:    128 byte
	 * 
	 * @var int
	 */
	const PAGE_SIZE = 128;
	
	private $name;
	
	public function getName(){
		return $this->name;
	}
	
	public function setName($name){
		
		if(!preg_match("/^[a-zA-Z0-9_]{1,64}$/is", $name)){
			throw new InvalidArgument("Invalid name '{$name}' given!");
		}
		$this->name = $name;
	}
	
	public function getType(){
		if($this->getEngine()===Engine::VIEW()){
			return Type::VIEW();
		}else{
			return Type::TABLE();
		}
	}
	
	public function setType(Type $type){
		switch($type){
			case Type::VIEW():
				$this->setEngine(Engine::VIEW());
				break;
				
			case Type::TABLE():
				if($this->getEngine() === Engine::VIEW()){
					$this->setEngine(Engine::INNODB());
				}
				break;
		}
	}
	
	private $engine;
	
	public function setEngine(Engine $engine){
		$this->engine = $engine;
	}
	
	public function getEngine(){
		if(is_null($this->engine)){
			$this->setEngine(Engine::MYISAM());
		}
		return $this->engine;
	}
	
	private $collation;
	
	public function setCollation($collation){
		$this->collation = (string)$collation;
		if(strlen($this->collation)>8){
			$this->collation = substr($this->collation, 0, 8);
		}
	}
	
	public function getCollation(){
		return $this->collation;
	}
	
	private $useChecksum = false;
	
	public function setUseChecksum($bool){
		$this->useChecksum = (bool)$bool;
	}
	
	public function getUseChecksum(){
		return $this->useChecksum;
	}
	
	private $maxRows;
	
	public function getMaxRows(){
		return $this->maxRows;
	}
	
	public function setMaxRows($maxRows){
		$this->maxRows = (int)$maxRows;
	}
	
	private $minRows;
	
	public function getMinRows(){
		return $this->minRows;
	}
	
	public function setMinRows($minRows){
		$this->minRows = (int)$minRows;
	}
	
	private $packKeys = false;
	
	public function setPackKeys($bool){
		$this->packKeys = (bool)$bool;
	}
	
	public function getPackKeys(){
		return $this->packKeys;
	}
	
	private $delayKeyWrite = false;
	
	public function setDelayKeyWrite($bool){
		$this->delayKeyWrite = (bool)$bool;
	}
	
	public function getDelayKeyWrite(){
		return $this->delayKeyWrite;
	}
	
	private $rowFormat;
	
	public function setRowFormat(RowFormat $format){
		$this->rowFormat = $format;
	}
	
	public function getRowFormat(){
		if(is_null($this->rowFormat)){
			$this->setRowFormat(RowFormat::FIXED());
		}
		return $this->rowFormat;
	}
	
	private $insertMethod;
	
	public function setInsertMethod(InsertMethod $method){
		$this->insertMethod = $method;
	}
	
	public function getInsertMethod(){
		if(is_null($this->insertMethod)){
			$this->setInsertMethod(InsertMethod::LAST());
		}
		return $this->insertMethod;
	}
	
	public function setData($data){
		
		if(!is_string($data) || strlen($data)!==self::PAGE_SIZE){
			throw new \InvalidArgumentException("Invalid page-data '{$data}' given!");
		}
		
		$rawName          = substr($data,  0, 64);
		$rawType          = substr($data, 64,  2);
		$rawCollate       = substr($data, 66,  8);
		$rawUseChecksum   = $data[74];
		$rawMaxRows       = substr($data, 75,  8);
		$rawMinRows       = substr($data, 83,  8);
		$rawPackKeys      = $data[91];
		$rawDelayKeyWrite = $data[92];
		$rawRowFormat     = substr($data, 93,  2);
		$rawInsertMethod  = substr($data, 95,  2);
		
		$name          = rtrim($rawName, "\0");
		$type          = unpack("n", $rawType)[1];
		$collate       = rtrim($rawCollate, "\0");
		$useChecksum   = (ord($rawUseChecksum) === 0x00) ?false :true;
		$rawMaxRows    = ltrim($rawMaxRows, "\0");
		$rawMinRows    = ltrim($rawMinRows, "\0");
		$packKeys      = (ord($rawPackKeys) === 0x00) ?false :true;
		$delayKeyWrite = (ord($rawDelayKeyWrite) === 0x00) ?false :true;
		$rowFormat     = unpack("n", $rawRowFormat)[1];
		$insertMethod  = unpack("n", $rawInsertMethod)[1];
		
		// convert any binary to integer
		$maxRows = hexdec(implode("", array_map(function($chr){
			return dechex(ord($chr));
		}, str_split($rawMaxRows))));
		
		// convert any binary to integer
		$minRows = hexdec(implode("", array_map(function($chr){
			return dechex(ord($chr));
		}, str_split($rawMinRows))));
		
		$this->setName($name);
		$this->setEngine(Engine::getByValue($type));
		$this->setCollation($collate);
		$this->setUseChecksum($useChecksum);
		$this->setMaxRows($maxRows);
		$this->setMinRows($minRows);
		$this->setPackKeys($packKeys);
		$this->setDelayKeyWrite($delayKeyWrite);
		$this->setRowFormat(RowFormat::getByValue($rowFormat));
		$this->setInsertMethod(InsertMethod::getByValue($insertMethod));
	}
	
	public function getData(){
		
		$name          = $this->getName();
		$engine        = $this->getEngine()->getValue();
		$collate       = $this->getCollation();
		$useChecksum   = $this->getUseChecksum();
		$maxRows       = $this->getMaxRows();
		$minRows       = $this->getMinRows();
		$packKeys      = $this->getPackKeys();
		$delayKeyWrite = $this->getDelayKeyWrite();
		$rowFormat     = $this->getRowFormat()->getValue();
		$insertMethod  = $this->getInsertMethod()->getValue();
		
		$rawName          = str_pad($name,   64, "\0", STR_PAD_RIGHT);
		$rawEngine        = pack("n", $engine);
		$rawCollate       = str_pad($collate, 8, "\0", STR_PAD_RIGHT);
		$rawUseChecksum   = $useChecksum   ?0x01 :0x00;
		$rawPackKeys      = $packKeys      ?0x01 :0x00;
		$rawDelayKeyWrite = $delayKeyWrite ?0x01 :0x00;
		$rawRowFormat     = pack("n", $rowFormat);
		$rawInsertMethod  = pack("n", $insertMethod);
		
		// convert any integer to binary
		$rawMaxRows = implode("", array_map(function($hex){
			return chr(hexdec($hex));
		}, str_split(dechex((string)$maxRows), 2)));
		$rawMaxRows = str_pad($rawMaxRows, 8, "\0", STR_PAD_LEFT);
		
		// convert any integer to binary
		$rawMinRows = implode("", array_map(function($hex){
			return chr(hexdec($hex));
		}, str_split(dechex((string)$minRows), 2)));
		$rawMinRows = str_pad($rawMinRows, 8, "\0", STR_PAD_LEFT);
		
		$data = "{$rawName}{$rawEngine}{$rawCollate}{$rawUseChecksum}{$rawMaxRows}{$rawMinRows}{$rawPackKeys}{$rawDelayKeyWrite}{$rawRowFormat}{$rawInsertMethod}";
		
		// fill reserved space with null-bytes
		$data = str_pad($data, self::PAGE_SIZE, "\0", STR_PAD_RIGHT);
		
		return $data;
	}
}
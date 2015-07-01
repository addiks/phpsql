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

namespace Addiks\Database\Entity\Index;

use Addiks\Database\Resource\CacheBackendInterface;

use Addiks\Database\Service\BinaryConverterTrait;

use Addiks\Common\Entity;

use Addiks\Protocol\Entity\Exception\Error;

use Addiks\Database\Entity\Storage;


/**
 * A hash-table is the most efficient indexing method to work with huge data.
 * The downside of working with hash-tables is that you can not iterate.
 *
 * @see http://en.wikipedia.org/wiki/table
 */
class HashTable extends Entity implements IndexInterface{
	
	/**
	 * Size of all stored references.
	 *
	 * 256 ^ 12 = ~80.000.000.000.000.000.000.000.000.000 bytes
	 *
	 * @var unknown_type
	 */
	const REFERENCE_SIZE = 12;
	
	const HASHTABLE_SIZE = 4194304; # = 4*1024*1024 = 4 MiB
	
	const DEBUG = false;
	
	use BinaryConverterTrait;
	
	public function __construct(Storage $storage){
		
		$this->storage = $storage;
		$this->storagePageCount = ceil(self::HASHTABLE_SIZE / self::REFERENCE_SIZE);
		$this->usedHashCharCount = ceil(log($this->storagePageCount, 16));
		$this->doublesBeginSeek = (int)(self::REFERENCE_SIZE * $this->storagePageCount);
		
		if($storage->getLength() <= 0){
			$this->clearAll();
		}
	}
	
	private $doublesBeginSeek;
	
	public function getDoublesBeginSeek(){
		return $this->doublesBeginSeek;
	}
	
	private $storage;
	
	public function getStorage(){
		return $this->storage;
	}
	
	private $storagePageCount;
	
	public function getStoragePageCount(){
		
		return $this->storagePageCount;
	}
	
	public function getDoublesStorage(){
	}
	
	public function setDoublesStorage(Storage $storage){
	}
	
	public function search($value){
		
		if(is_null($value)){
			throw new Error("Parameter \$value cannot be NULL!");
		}
		
		if(is_int($value)){
			$value = $this->decstr($value);
		}
		
		$handle = $this->getStorage()->getHandle();
		$beforeSeek = ftell($handle);
		$hashIndex = $this->getHashedInteger($value);
		
		$cachedResult = null;
		if(!is_null($this->getCacheBackend())){
			$cachedResult = $this->getCacheBackend()->get($value);
			if(strlen($cachedResult)>0){
				$cachedResult = substr($cachedResult, 1);
				$cachedResult = explode("\0", $cachedResult);
			}
		}
		
		if(is_array($cachedResult)){
			$keys = $cachedResult;
			
		}else{
			
			fseek($handle, $hashIndex * self::REFERENCE_SIZE, SEEK_SET);
			
			/**
			 * This is a loop-prevention to checks if the current index has already been visited.
			 * @var array
			 */
			$walkedIndicies = array();
			
			$keys = array();
			
			$seek = fread($handle, self::REFERENCE_SIZE);
			
			while(ltrim($seek, "\0")!==""){
				$seek = $this->strdec($seek);
					
				if(isset($walkedIndicies[$seek])){
					throw new Error("Reference-Loop in HashTable-Doubles-Storage occoured!");
				}
				$walkedIndicies[$seek] = $seek;
					
				fseek($handle, $seek, SEEK_SET);
					
				$checkLength = fread($handle, self::REFERENCE_SIZE);
				$checkLength = $this->strdec($checkLength);
				if($checkLength <= 0){
					throw new Error("Found length-specification for check-value in hash-table which is lower or equal 0!");
				}
				$checkValue  = fread($handle, $checkLength);
				$dataLength  = fread($handle, self::REFERENCE_SIZE);
				$dataLength  = $this->strdec($dataLength);
				if($dataLength <= 0){
					throw new Error("Found length-specification for data-value in hash-table which is lower or equal 0!");
				}
				$data        = fread($handle, $dataLength);
				$seek        = fread($handle, self::REFERENCE_SIZE);
					
				if($checkValue === $value){
					$keys[] = $data;
				}
			}
			
			fseek($handle, $beforeSeek, SEEK_SET);
			
			if(!is_null($this->getCacheBackend())){
				$this->getCacheBackend()->set($value, "\0".implode("\0", $keys));
			}
		}
		
		return $keys;
	}
	
	/**
	 * Inserts a new value into the hash-table.
	 *
	 * @see Addiks\Database.Interface::insert()
	 */
	public function insert($value, $rowId){
		
		### VALUE CLEANING
		
		if(is_null($value)){
			throw new Error("Parameter \$value cannot be NULL!");
		}
		
		if(is_null($rowId)){
			throw new Error("Parameter \$rowId cannot be NULL!");
		}
		
		if(is_int($value)){
			$value = $this->decstr($value);
		}
		
		if(is_int($rowId)){
			$rowId = $this->decstr($rowId);
		}
		
		if(!is_null($this->getCacheBackend())){
			$this->getCacheBackend()->add($value, "\0" . $rowId);
		}
		
		$handle = $this->getStorage()->getHandle();
		$beforeSeek = ftell($handle);
		$hashSeek = $this->getHashedInteger($value);
		
		fseek($handle, $hashSeek * self::REFERENCE_SIZE, SEEK_SET);
		
		$seek = fread($handle, self::REFERENCE_SIZE);
		
		if(ltrim($seek, "\0") === ""){
			
			### CREATE NEW CELL
			
			$writeSeek = $hashSeek * self::REFERENCE_SIZE;
			
		}else{
			
			### APPEND TO EXISTING CELL
			
			/**
			 * This is a loop-prevention to check if the current index has already been visited.
			 * @var array
			 */
			$walkedIndicies = array();
			
			do{
				$seek = $this->strdec($seek);
				fseek($handle, $seek, SEEK_SET);
					
				if(isset($walkedIndicies[$seek])){
					fseek($handle, $beforeSeek, SEEK_SET);
					throw new Error("Reference-Loop in HashTable-Doubles-Storage occoured!");
				}
				$walkedIndicies[$seek] = $seek;
					
				$checkLength = fread($handle, self::REFERENCE_SIZE);
				$checkValue  = fread($handle, $this->strdec($checkLength));
				$dataLength  = fread($handle, self::REFERENCE_SIZE);
				$data        = fread($handle, $this->strdec($dataLength));
				$seek        = fread($handle, self::REFERENCE_SIZE);
				
				if($this->strdec($seek) > fstat($handle)['size']){
					fseek($handle, $beforeSeek, SEEK_SET);
					throw new Error("Invalid reference in hash-table found!");
				}
				
			}while(ltrim($seek, "\0") !== "");
			
			fseek($handle, 0-self::REFERENCE_SIZE, SEEK_CUR);
			$writeSeek = ftell($handle);
		}
		
		fseek($handle, 0, SEEK_END);
		$seek = ftell($handle);
		
		if(log($seek, 256) > self::REFERENCE_SIZE){
			fseek($handle, $beforeSeek, SEEK_SET);
			throw new Conflict("Hash-Table is full! (Can not address more data!)");
		}
			
		$valueLength = $this->decstr(strlen($value), self::REFERENCE_SIZE);
		$rowIdLength = $this->decstr(strlen($rowId), self::REFERENCE_SIZE);
			
		fwrite($handle, $valueLength);
		fwrite($handle, $value);
		fwrite($handle, $rowIdLength);
		fwrite($handle, $rowId);
		fwrite($handle, str_pad("", self::REFERENCE_SIZE, "\0"));
			
		// store the reference to the value in the hash-table
		fseek($handle, $writeSeek, SEEK_SET);
		fwrite($handle, $this->decstr($seek, self::REFERENCE_SIZE));

		if(self::DEBUG){
			fseek($handle, 0, SEEK_END);
			if(ftell($handle) > (50 * 1024 * 1024)){
				var_dump([$value, $rowId, $hashSeek, $writeSeek, $seek]);
				throw new Error("WAAAAIT! Something wrong here!");
			}
		}
		
		fseek($handle, $beforeSeek, SEEK_SET);
		
		if(!in_array($rowId, $this->search($value))){
			throw new Error("Value not found in hash-table after inserting it!");
		}
		
		if(self::DEBUG){
			$this->performSelfTest();
		}
	}
	
	public function remove($value, $rowId){
		
		if(is_null($value)){
			throw new Error("Parameter \$value cannot be NULL!");
		}
		
		if(is_null($rowId)){
			throw new Error("Parameter \$rowId cannot be NULL!");
		}
		
		if(is_int($value)){
			$value = $this->decstr($value);
		}
		
		if(is_int($rowId)){
			$rowId = $this->decstr($rowId);
		}
		
		if(!is_null($this->getCacheBackend())){
			$this->getCacheBackend()->remove($value);
		}
		
		if(!is_null($this->getCacheBackend())){
			$cachedString = $this->getCacheBackend()->get($value);
			$cachedString = str_replace("\0{$rowId}", "", $cachedString);
			$this->getCacheBackend()->set($value, $cachedString);
		}
		
		$handle = $this->getStorage()->getHandle();
		$beforeSeek = ftell($handle);
		$hashSeek = $this->getHashedInteger($value);
		
		fseek($handle, $hashSeek * self::REFERENCE_SIZE, SEEK_SET);
		
		$seek = fread($handle, self::REFERENCE_SIZE);
		
		if(ltrim($seek, "\0") === ""){
			return;
		}
		
		/**
		 * This is a loop-prevention to check if the current index has already been visited.
		 * @var array
		 */
		$walkedIndicies = array();
		
		do{
			$seek = $this->strdec($seek);
			fseek($handle, $seek, SEEK_SET);
				
			if(isset($walkedIndicies[$seek])){
				fseek($handle, $beforeSeek, SEEK_SET);
				throw new Error("Reference-Loop in HashTable-Doubles-Storage occoured!");
			}
			$walkedIndicies[$seek] = $seek;
				
			$checkLength = fread($handle, self::REFERENCE_SIZE);
			$checkValue  = fread($handle, $this->strdec($checkLength));
			$dataLength  = fread($handle, self::REFERENCE_SIZE);
			$data        = fread($handle, $this->strdec($dataLength));
		
			if($checkValue === $value && $data === $rowId){
				fseek($handle, $seek, SEEK_SET);
				
				$checkLength = fread($handle, self::REFERENCE_SIZE);
				fwrite($handle, str_pad("", $this->strdec($checkLength), "\0"));
				$dataLength  = fread($handle, self::REFERENCE_SIZE);
				fwrite($handle, str_pad("", $this->strdec($dataLength), "\0"));
			}
			
			$seek = fread($handle, self::REFERENCE_SIZE);
			
		}while(ltrim($seek, "\0") !== "");
		
		fseek($handle, $beforeSeek, SEEK_SET);

		if(self::DEBUG){
			$this->performSelfTest();
		}
		
		if(in_array($rowId, $this->search($value))){
			throw new Error("Value still found in hash-table after removing it!");
		}
	}
	
	public function clearAll(){
		
		$handle = $this->getStorage()->getHandle();
		ftruncate($handle, 0);
		fflush($handle);
		fseek($handle, $this->getDoublesBeginSeek(), SEEK_SET);
		fwrite($handle, "\0");
		fflush($handle);
	}
	
	### HELPER
	
	private $usedHashCharCount;
	
	public function getHashedInteger($key){
		
		if(is_int($key)){
			$key = $this->decstr($key, self::REFERENCE_SIZE);
		}
		
		$key = ltrim($key, "\0");
		
		$hash = substr(md5($key), 0, $this->usedHashCharCount);
		
		$dec = hexdec($hash);
		
		$dec = $dec % $this->storagePageCount;
		
		return $dec;
	}
	
	### DUMP
	
	public function dumpToArray(){
		
		$handle = $this->getStorage()->getHandle();
		$beforeSeek = ftell($handle);
		fseek($handle, $this->getDoublesBeginSeek()+1, SEEK_SET);
		
		$array = array();
		
		while(!feof($handle)){
				
			$checkLength = fread($handle, self::REFERENCE_SIZE);
			$checkValue  = fread($handle, $this->strdec($checkLength));
			$dataLength  = fread($handle, self::REFERENCE_SIZE);
			$data        = fread($handle, $this->strdec($dataLength));
			$seek        = fread($handle, self::REFERENCE_SIZE);
				
			if(ltrim($checkValue, "\0")!== ""){
				if(!isset($array[$checkValue])){
					$array[$checkValue] = array();
				}
				$array[$checkValue][] = $data;
			}
		}
		
		fseek($handle, $beforeSeek, SEEK_SET);
		
		return $array;
	}
	
	public function dumpToLog($logger){
		
		$handle = $this->getStorage()->getHandle();
		$beforeSeek = ftell($handle);
		fseek($handle, $this->getDoublesBeginSeek()+1, SEEK_SET);
		
		while(!feof($handle)){
			
			$checkLength = fread($handle, self::REFERENCE_SIZE);
			$checkValue  = fread($handle, $this->strdec($checkLength));
			$dataLength  = fread($handle, self::REFERENCE_SIZE);
			$data        = fread($handle, $this->strdec($dataLength));
			$seek        = fread($handle, self::REFERENCE_SIZE);
			
			if(ltrim($checkValue, "\0")!== ""){
				$logger->log("{$checkValue}: {$data}");
			}
		}
		
		fseek($handle, $beforeSeek, SEEK_SET);
	}
	
	protected function performSelfTest(){
		
		$handle = $this->getStorage()->getHandle();
		$beforeSeek = ftell($handle);

		fseek($handle, 0, SEEK_END);
		
		$size = ftell($handle);
		
		fseek($handle, 0, SEEK_SET);
			
		for($index=0;$index<$this->storagePageCount;$index++){
			$reference = fread($handle, self::REFERENCE_SIZE);
			if(ltrim($reference, "\0")!==''){
				$reference = $this->strdec($reference);
				if($reference >= $size){
					throw new Error("Broken hash-table detected! (Reference '{$reference}' in hashtable points beyond end '{$size}' near seek '".ftell($handle)."'!)");
				}
			}
		}

		fseek($handle, $this->doublesBeginSeek+1, SEEK_SET);

		while(!feof($handle)){
			
			### CHECK
			
			$checkLength = fread($handle, self::REFERENCE_SIZE);
			
			if($checkLength === ""){
				break; // reached end
			}
			
			$checkLength = $this->strdec($checkLength);
			
			if($checkLength <= 0){
				throw new Error("Broken hash-table detected! (Check-length cannot be 0 near seek '".ftell($handle)."'!)");
			}elseif($checkLength + ftell($handle) > $size){
				throw new Error("Broken hash-table detected! (Check-length '{$checkLength}' reads beyond data-end '{$size}' near seek '".ftell($handle)."'!)");
			}
			
			$checkData   = fread($handle, $checkLength);
			
			### VALUE
			
			$valueLength = fread($handle, self::REFERENCE_SIZE);
			$valueLength = $this->strdec($valueLength);

			if($valueLength <= 0){
				throw new Error("Broken hash-table detected! (Data-length cannot be 0 near seek '".ftell($handle)."'!)");
			}elseif($valueLength + ftell($handle) > $size){
				throw new Error("Broken hash-table detected! (Data-length '{$valueLength}' reads beyond data-end '{$size}' near seek '".ftell($handle)."'!)");
			}
				
			$valueData   = fread($handle, $valueLength);
			
			### FOLLOWUP
			
			$reference   = fread($handle, self::REFERENCE_SIZE);
			
			if(ltrim($reference, "\0")!==''){
				$reference = $this->strdec($reference);
				if($reference >= $size){
					throw new Error("Broken hash-table detected! (Followup-reference '{$reference}' points beyond end '{$size}' near seek '".ftell($handle)."'!)");
				}
			}
		}
		
		fseek($handle, $beforeSeek, SEEK_SET);
	}
	
	### CACHE-BACKEND
	
	private $cacheBackend;
	
	public function setCacheBackend(CacheBackendInterface $cacheBackend = null){
		$this->cacheBackend = $cacheBackend;
	}
	
	public function getCacheBackend(){
		return $this->cacheBackend;
	}
}
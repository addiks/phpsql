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

namespace Addiks\PHPSQL\Index;

use ErrorException;
use InvalidArgumentException;
use Addiks\PHPSQL\CacheBackendInterface;
use Addiks\PHPSQL\BinaryConverterTrait;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;

/**
 * A hash-table is the most efficient indexing method to work with huge data.
 * The downside of working with hash-tables is that you can not iterate.
 *
 * @see http://en.wikipedia.org/wiki/table
 */
class HashTable implements IndexInterface
{
    
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
    
    public function __construct(FileResourceProxy $file)
    {
        
        $this->file = $file;
        $this->filePageCount = ceil(self::HASHTABLE_SIZE / self::REFERENCE_SIZE);
        $this->usedHashCharCount = ceil(log($this->filePageCount, 16));
        $this->doublesBeginSeek = (int)(self::REFERENCE_SIZE * $this->filePageCount);
        
        if ($file->getLength() <= 0) {
            $this->clearAll();
        }
    }
    
    private $doublesBeginSeek;
    
    public function getDoublesBeginSeek()
    {
        return $this->doublesBeginSeek;
    }
    
    private $file;
    
    public function getFile()
    {
        return $this->file;
    }
    
    private $filePageCount;
    
    public function getFilePageCount()
    {
        
        return $this->filePageCount;
    }
    
    public function getDoublesFile()
    {
    }
    
    public function setDoublesFile(FileResourceProxy $file)
    {
    }
    
    public function search($value)
    {
        
        if (is_null($value)) {
            throw new ErrorException("Parameter \$value cannot be NULL!");
        }
        
        if (is_int($value)) {
            $value = $this->decstr($value);
        }
        
        $file = $this->getFile();
        $beforeSeek = $file->tell();
        $hashIndex = $this->getHashedInteger($value);
        
        $cachedResult = null;
        if (!is_null($this->getCacheBackend())) {
            $cachedResult = $this->getCacheBackend()->get($value);
            if (strlen($cachedResult)>0) {
                $cachedResult = substr($cachedResult, 1);
                $cachedResult = explode("\0", $cachedResult);
            }
        }
        
        if (is_array($cachedResult)) {
            $keys = $cachedResult;
            
        } else {
            $file->seek($hashIndex * self::REFERENCE_SIZE, SEEK_SET);
            
            /**
             * This is a loop-prevention to checks if the current index has already been visited.
             * @var array
             */
            $walkedIndicies = array();
            
            $keys = array();
            
            $seek = $file->read(self::REFERENCE_SIZE);
            
            while (ltrim($seek, "\0")!=="") {
                $seek = $this->strdec($seek);
                    
                if (isset($walkedIndicies[$seek])) {
                    throw new ErrorException("Reference-Loop in HashTable-Doubles-File occoured!");
                }
                $walkedIndicies[$seek] = $seek;
                    
                $file->seek($seek, SEEK_SET);
                    
                $checkLength = $file->read(self::REFERENCE_SIZE);
                $checkLength = $this->strdec($checkLength);
                if ($checkLength <= 0) {
                    throw new ErrorException("Found length-specification for check-value in hash-table which is lower or equal 0!");
                }
                $checkValue  = $file->read($checkLength);
                $dataLength  = $file->read(self::REFERENCE_SIZE);
                $dataLength  = $this->strdec($dataLength);
                if ($dataLength <= 0) {
                    throw new ErrorException("Found length-specification for data-value in hash-table which is lower or equal 0!");
                }
                $data        = $file->read($dataLength);
                $seek        = $file->read(self::REFERENCE_SIZE);
                    
                if ($checkValue === $value) {
                    $keys[] = $data;
                }
            }
            
            $file->seek($beforeSeek, SEEK_SET);
            
            if (!is_null($this->getCacheBackend())) {
                $this->getCacheBackend()->set($value, "\0".implode("\0", $keys));
            }
        }
        
        return $keys;
    }
    
    /**
     * Inserts a new value into the hash-table.
     *
     * @see Addiks\PHPSQL.Interface::insert()
     */
    public function insert($value, $rowId)
    {
        
        ### VALUE CLEANING
        
        if (is_null($value)) {
            throw new ErrorException("Parameter \$value cannot be NULL!");
        }
        
        if (is_null($rowId)) {
            throw new ErrorException("Parameter \$rowId cannot be NULL!");
        }
        
        if (is_int($value)) {
            $value = $this->decstr($value);
        }
        
        if (is_int($rowId)) {
            $rowId = $this->decstr($rowId);
        }
        
        if (!is_null($this->getCacheBackend())) {
            $this->getCacheBackend()->add($value, "\0" . $rowId);
        }
        
        $file = $this->getFile();
        $beforeSeek = $file->tell();
        $hashSeek = $this->getHashedInteger($value);
        
        $file->seek($hashSeek * self::REFERENCE_SIZE, SEEK_SET);
        
        $seek = $file->read(self::REFERENCE_SIZE);
        
        if (ltrim($seek, "\0") === "") {
            ### CREATE NEW CELL
            
            $writeSeek = $hashSeek * self::REFERENCE_SIZE;
            
        } else {
            ### APPEND TO EXISTING CELL
            
            /**
             * This is a loop-prevention to check if the current index has already been visited.
             * @var array
             */
            $walkedIndicies = array();
            
            do {
                $seek = $this->strdec($seek);
                $file->seek($seek, SEEK_SET);
                    
                if (isset($walkedIndicies[$seek])) {
                    $file->seek($beforeSeek, SEEK_SET);
                    throw new ErrorException("Reference-Loop in HashTable-Doubles-File occoured!");
                }
                $walkedIndicies[$seek] = $seek;
                    
                $checkLength = $file->read(self::REFERENCE_SIZE);
                $checkValue  = $file->read($this->strdec($checkLength));
                $dataLength  = $file->read(self::REFERENCE_SIZE);
                $data        = $file->read($this->strdec($dataLength));
                $seek        = $file->read(self::REFERENCE_SIZE);
                
                if ($this->strdec($seek) > $file->getSize()) {
                    $file->seek($beforeSeek, SEEK_SET);
                    throw new ErrorException("Invalid reference in hash-table found!");
                }
                
            } while (ltrim($seek, "\0") !== "");
            
            $file->seek(0-self::REFERENCE_SIZE, SEEK_CUR);
            $writeSeek = $file->tell();
        }
        
        $file->seek(0, SEEK_END);
        $seek = $file->tell();
        
        if (log($seek, 256) > self::REFERENCE_SIZE) {
            $file->seek($beforeSeek, SEEK_SET);
            throw new InvalidArgumentException("Hash-Table is full! (Can not address more data!)");
        }
            
        $valueLength = $this->decstr(strlen($value), self::REFERENCE_SIZE);
        $rowIdLength = $this->decstr(strlen($rowId), self::REFERENCE_SIZE);
            
        $file->write($valueLength);
        $file->write($value);
        $file->write($rowIdLength);
        $file->write($rowId);
        $file->write(str_pad("", self::REFERENCE_SIZE, "\0"));
            
        // store the reference to the value in the hash-table
        $file->seek($writeSeek, SEEK_SET);
        $file->write($this->decstr($seek, self::REFERENCE_SIZE));

        if (self::DEBUG) {
            $file->seek(0, SEEK_END);
            if ($file->tell() > (50 * 1024 * 1024)) {
                var_dump([$value, $rowId, $hashSeek, $writeSeek, $seek]);
                throw new ErrorException("WAAAAIT! Something wrong here!");
            }
        }
        
        $file->seek($beforeSeek, SEEK_SET);
        
        if (!in_array($rowId, $this->search($value))) {
            throw new ErrorException("Value not found in hash-table after inserting it!");
        }
        
        if (self::DEBUG) {
            $this->performSelfTest();
        }
    }
    
    public function remove($value, $rowId)
    {
        
        if (is_null($value)) {
            throw new ErrorException("Parameter \$value cannot be NULL!");
        }
        
        if (is_null($rowId)) {
            throw new ErrorException("Parameter \$rowId cannot be NULL!");
        }
        
        if (is_int($value)) {
            $value = $this->decstr($value);
        }
        
        if (is_int($rowId)) {
            $rowId = $this->decstr($rowId);
        }
        
        if (!is_null($this->getCacheBackend())) {
            $this->getCacheBackend()->remove($value);
        }
        
        if (!is_null($this->getCacheBackend())) {
            $cachedString = $this->getCacheBackend()->get($value);
            $cachedString = str_replace("\0{$rowId}", "", $cachedString);
            $this->getCacheBackend()->set($value, $cachedString);
        }
        
        $file = $this->getFile();
        $beforeSeek = $file->tell();
        $hashSeek = $this->getHashedInteger($value);
        
        $file->seek($hashSeek * self::REFERENCE_SIZE, SEEK_SET);
        
        $seek = $file->read(self::REFERENCE_SIZE);
        
        if (ltrim($seek, "\0") === "") {
            return;
        }
        
        /**
         * This is a loop-prevention to check if the current index has already been visited.
         * @var array
         */
        $walkedIndicies = array();
        
        do {
            $seek = $this->strdec($seek);
            $file->seek($seek, SEEK_SET);
                
            if (isset($walkedIndicies[$seek])) {
                $file->seek($beforeSeek, SEEK_SET);
                throw new ErrorException("Reference-Loop in HashTable-Doubles-File occoured!");
            }
            $walkedIndicies[$seek] = $seek;
                
            $checkLength = $file->read(self::REFERENCE_SIZE);
            $checkValue  = $file->read($this->strdec($checkLength));
            $dataLength  = $file->read(self::REFERENCE_SIZE);
            $data        = $file->read($this->strdec($dataLength));
        
            if ($checkValue === $value && $data === $rowId) {
                $file->seek($seek, SEEK_SET);
                
                $checkLength = $file->read(self::REFERENCE_SIZE);
                $file->write(str_pad("", $this->strdec($checkLength), "\0"));
                $dataLength  = $file->read(self::REFERENCE_SIZE);
                $file->write(str_pad("", $this->strdec($dataLength), "\0"));
            }
            
            $seek = $file->read(self::REFERENCE_SIZE);
            
        } while (ltrim($seek, "\0") !== "");
        
        $file->seek($beforeSeek, SEEK_SET);

        if (self::DEBUG) {
            $this->performSelfTest();
        }
        
        if (in_array($rowId, $this->search($value))) {
            throw new ErrorException("Value still found in hash-table after removing it!");
        }
    }
    
    public function clearAll()
    {
        $file = $this->getFile();
        $file->truncate(0);
        $file->flush();
        $file->seek($this->getDoublesBeginSeek(), SEEK_SET);
        $file->write("\0");
        $file->flush();
    }
    
    ### HELPER
    
    private $usedHashCharCount;
    
    public function getHashedInteger($key)
    {
        
        if (is_int($key)) {
            $key = $this->decstr($key, self::REFERENCE_SIZE);
        }
        
        $key = ltrim($key, "\0");
        
        $hash = substr(md5($key), 0, $this->usedHashCharCount);
        
        $dec = hexdec($hash);
        
        $dec = $dec % $this->filePageCount;
        
        return $dec;
    }
    
    ### DUMP
    
    public function dumpToArray()
    {
        $file = $this->getFile();
        $beforeSeek = $file->tell();
        $file->seek($this->getDoublesBeginSeek()+1, SEEK_SET);
        
        $array = array();
        
        while (!$file->eof()) {
            $checkLength = $file->read(self::REFERENCE_SIZE);
            $checkValue  = $file->read($this->strdec($checkLength));
            $dataLength  = $file->read(self::REFERENCE_SIZE);
            $data        = $file->read($this->strdec($dataLength));
            $seek        = $file->read(self::REFERENCE_SIZE);
                
            if (ltrim($checkValue, "\0")!== "") {
                if (!isset($array[$checkValue])) {
                    $array[$checkValue] = array();
                }
                $array[$checkValue][] = $data;
            }
        }
        
        $file->seek($beforeSeek, SEEK_SET);
        
        return $array;
    }
    
    public function dumpToLog($logger)
    {
        
        $file = $this->getFile();
        $beforeSeek = $file->tell();
        $file->seek($this->getDoublesBeginSeek()+1, SEEK_SET);
        
        while (!$file->eof()) {
            $checkLength = $file->read(self::REFERENCE_SIZE);
            $checkValue  = $file->read($this->strdec($checkLength));
            $dataLength  = $file->read(self::REFERENCE_SIZE);
            $data        = $file->read($this->strdec($dataLength));
            $seek        = $file->read(self::REFERENCE_SIZE);
            
            if (ltrim($checkValue, "\0")!== "") {
                $logger->log("{$checkValue}: {$data}");
            }
        }
        
        $file->seek($beforeSeek, SEEK_SET);
    }
    
    protected function performSelfTest()
    {
        
        $file = $this->getFile();
        $beforeSeek = $file->tell();

        $file->seek(0, SEEK_END);
        
        $size = $file->tell();
        
        $file->seek(0, SEEK_SET);
            
        for ($index=0; $index<$this->filePageCount; $index++) {
            $reference = $file->read(self::REFERENCE_SIZE);
            if (ltrim($reference, "\0")!=='') {
                $reference = $this->strdec($reference);
                if ($reference >= $size) {
                    throw new ErrorException("Broken hash-table detected! (Reference '{$reference}' in hashtable points beyond end '{$size}' near seek '".$file->tell()."'!)");
                }
            }
        }

        $file->seek($this->doublesBeginSeek+1, SEEK_SET);

        while (!$file->eof()) {
            ### CHECK
            
            $checkLength = $file->read(self::REFERENCE_SIZE);
            
            if ($checkLength === "") {
                break; // reached end
            }
            
            $checkLength = $this->strdec($checkLength);
            
            if ($checkLength <= 0) {
                throw new ErrorException("Broken hash-table detected! (Check-length cannot be 0 near seek '".$file->tell()."'!)");
            } elseif ($checkLength + $file->tell() > $size) {
                throw new ErrorException("Broken hash-table detected! (Check-length '{$checkLength}' reads beyond data-end '{$size}' near seek '".$file->tell()."'!)");
            }
            
            $checkData   = $file->read($checkLength);
            
            ### VALUE
            
            $valueLength = $file->read(self::REFERENCE_SIZE);
            $valueLength = $this->strdec($valueLength);

            if ($valueLength <= 0) {
                throw new ErrorException("Broken hash-table detected! (Data-length cannot be 0 near seek '".$file->tell()."'!)");
            } elseif ($valueLength + $file->tell() > $size) {
                throw new ErrorException("Broken hash-table detected! (Data-length '{$valueLength}' reads beyond data-end '{$size}' near seek '".$file->tell()."'!)");
            }
                
            $valueData   = $file->read($valueLength);
            
            ### FOLLOWUP
            
            $reference   = $file->read(self::REFERENCE_SIZE);
            
            if (ltrim($reference, "\0")!=='') {
                $reference = $this->strdec($reference);
                if ($reference >= $size) {
                    throw new ErrorException("Broken hash-table detected! (Followup-reference '{$reference}' points beyond end '{$size}' near seek '".$file->tell()."'!)");
                }
            }
        }
        
        $file->seek($beforeSeek, SEEK_SET);
    }
    
    ### CACHE-BACKEND
    
    private $cacheBackend;
    
    public function setCacheBackend(CacheBackendInterface $cacheBackend = null)
    {
        $this->cacheBackend = $cacheBackend;
    }
    
    public function getCacheBackend()
    {
        return $this->cacheBackend;
    }
}

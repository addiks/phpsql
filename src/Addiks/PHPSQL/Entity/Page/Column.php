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

namespace Addiks\PHPSQL\Entity\Page;

use Addiks\Analyser\Service\TokenParser\CodeBlock\DocComment;
use Addiks\PHPSQL\Value\Enum\Page\Column\DataType;
use Addiks\PHPSQL\Service\BinaryConverterTrait;
use Addiks\PHPSQL\Entity;
use Addiks\Common\Tool\ClassAnalyzer;
use ErrorException;

/**
 * A page in an table-index containing information about a column in the table.
 *
 * name:       1024bit  128byte
 * datatype:     16bit    2byte
 * length:      384bit   48byte
 * extra:        16bit    2byte
 * fk_table      64bit    8byte
 * fk_column     32bit    4byte
 * reserved              64byte
 * __________________
 *             2048bit  256byte
 */
class Column extends Entity
{
    
    use BinaryConverterTrait;
    
    const PAGE_SIZE = 256;
    
    /**
     * The name of the column.
     * (1024 bit / 128byte)
     * @var string
     */
    private $name;
    
    public function setName($name)
    {
        
        $pattern = "^[a-zA-Z0-9_-]{1,128}$";
        if (!preg_match("/{$pattern}/is", $name)) {
            throw new \InvalidArgumentException("Invalid column name '{$name}' does not match pattern '{$pattern}'!");
        }
        
        $this->name = $name;
    }
    
    public function getName()
    {
        return $this->name;
    }
    
    /**
     * Specifies the data-type
     * @var DataType
     */
    private $datatype;
    
    public function setDataType(DataType $dataType)
    {
        $this->datatype = $dataType;
    }
    
    /**
     * @return DataType
     */
    public function getDataType()
    {
        if (is_null($this->datatype)) {
            $this->datatype = DataType::TINYTEXT();
        }
        return $this->datatype;
    }
    
    /**
     * Maximum length of the column-data.
     * (saved in 384bit)
     * @var int
     */
    private $length;
    
    public function setLength($length)
    {
        if (!is_int($length) || strlen(decbin($length))>384) {
            throw new \InvalidArgumentException("Invalid length '{$length}' specified for column!");
        }
        $this->length = $length;
    }
    
    public function getLength()
    {
        return $this->length;
    }
    
    /**
     * Second length parameter, mostly used for float-comma-numbers.
     * (saved in 384bit)
     * @var int
     */
    private $secondLength;
    
    public function setSecondLength($length)
    {
        if (!is_int($length) || strlen(decbin($length))>384) {
            throw new \InvalidArgumentException("Invalid length '{$length}' specified for column!");
        }
        $this->secondLength = $length;
    }
    
    public function getSecondLength()
    {
        return $this->secondLength;
    }
    
    const EXTRA_PRIMARY_KEY     = 0x01;
    const EXTRA_NOT_NULL        = 0x02;
    const EXTRA_UNSIGNED        = 0x04;
    const EXTRA_ZEROFILL        = 0x08;
    const EXTRA_UNIQUE_KEY      = 0x10;
    const EXTRA_AUTO_INCREMENT  = 0x20;
    
    public function isNotNull()
    {
        return (bool)(($this->getExtraFlags() & self::EXTRA_NOT_NULL) === self::EXTRA_NOT_NULL);
    }
    
    public function isPrimaryKey()
    {
        return (bool)(($this->getExtraFlags() & self::EXTRA_PRIMARY_KEY) === self::EXTRA_PRIMARY_KEY);
    }
    
    public function isUniqueKey()
    {
        return (bool)(($this->getExtraFlags() & self::EXTRA_UNIQUE_KEY) === self::EXTRA_UNIQUE_KEY);
    }
    
    public function isAutoIncrement()
    {
        return (bool)(($this->getExtraFlags() & self::EXTRA_AUTO_INCREMENT) === self::EXTRA_AUTO_INCREMENT);
    }
    
    /**
     * Flags containing special information about the column.
     * @var int
     * @see self::EXTRA_*
     */
    private $extraFlags;
    
    public function setExtraFlags($flags)
    {
        if (!is_int($flags) || $flags < 0 || $flags > 65535) {
            throw new \InvalidArgumentException("Invalid extra-flags-value '{$flags}' given to column!");
        }
        $this->extraFlags = $flags;
    }
    
    public function getExtraFlags()
    {
        return $this->extraFlags;
    }
    
    ### FOREIGN KEYS
    
    private $fkTableIndex;
    
    public function setFKTableIndex($index)
    {
        if (!is_int($index) || $index<0) {
            throw new \InvalidArgumentException("Invalid FK-table-index '{$index}' given to column!");
        }
        $this->fkTableIndex = $index;
    }
    
    public function getFKTableIndex()
    {
        return $this->fkTableIndex;
    }
    
    private $fkColumnIndex;
    
    public function setFKColumnIndex($index)
    {
        if (!is_int($index) || $index<0) {
            throw new \InvalidArgumentException("Invalid FK-column-index '{$index}' given to column!");
        }
        $this->fkColumnIndex = $index;
    }
    
    public function getFKColumnIndex()
    {
        return $this->fkColumnIndex;
    }
    
    ### PERSISTANCE
    
    public function setData($data)
    {
        
        if (strlen($data) !== self::PAGE_SIZE) {
            throw new \InvalidArgumentException("Invalid data-block given to column-page! (length ".strlen($data)." != ".self::PAGE_SIZE.")");
        }
        
        $rawName     = substr($data, 0, 128);
        $rawDataType = substr($data, 128, 2);
        $rawLength   = substr($data, 130, 48);
        $rawExtra    = substr($data, 178, 2);
        $rawFkTable  = substr($data, 180, 8);
        $rawFkColumn = substr($data, 188, 4);
        
        $name     = rtrim($rawName, "\0");
        $length   = ltrim($rawLength, "\0");
        $fkTable  = ltrim($rawFkTable, "\0");
        $fkColumn = ltrim($rawFkColumn, "\0");
        
        $dataType = unpack("n", $rawDataType)[1];
        $length   = $this->strdec($length);
        $extra    = unpack("n", $rawExtra)[1];
        $fkTable  = $this->strdec($fkTable);
        $fkColumn = $this->strdec($fkColumn);
        
        $this->setName($name);
        $this->setDataType(DataType::getByValue($dataType));
        $this->setLength($length);
        $this->setExtraFlags($extra);
        $this->setFKTableIndex($fkTable);
        $this->setFKColumnIndex($fkColumn);
    }
    
    public function getData()
    {
        
        $name          = $this->getName();
        $dataType      = $this->getDataType()->getValue();
        $length        = $this->getLength();
        $extra         = $this->getExtraFlags();
        $fkTableIndex  = $this->getFKTableIndex();
        $fkColumnIndex = $this->getFKColumnIndex();
        
        $rawDataType = pack("n", $dataType);
        $rawLength   = $this->decstr($length);
        $rawExtra    = pack("n", $extra);
        $rawFkTable  = $this->decstr($fkTableIndex);
        $rawFkColumn = $this->decstr($fkColumnIndex);
        
        $rawName     = str_pad($name, 128, "\0", STR_PAD_RIGHT);
        $rawLength   = str_pad($rawLength, 48, "\0", STR_PAD_LEFT);
        $rawFkTable  = str_pad($rawFkTable, 8, "\0", STR_PAD_LEFT);
        $rawFkColumn = str_pad($rawFkColumn, 4, "\0", STR_PAD_LEFT);
        
        $data = $rawName.
                $rawDataType.
                $rawLength.
                $rawExtra.
                $rawFkTable.
                $rawFkColumn;
        
        # fill reserved space
        $data = str_pad($data, self::PAGE_SIZE, "\0", STR_PAD_RIGHT);
        
        if (strlen($data) !== self::PAGE_SIZE) {
            throw new ErrorException("Invalid page-data generated for column-page! (length ".strlen($data)." !== ".self::PAGE_SIZE.")");
        }
        
        return $data;
    }
    
    ### HELPER
    
    /**
     * A cell-size keyword to indicate that the value should be stored in its own storage.
     * @see Storage
     * @var string
     */
    const LENGTH_STORAGE = "storage";
    
    private $cellsizeCache = null;
    
    /**
     * Gets the size of a cell from this column in bytes.
     * If string, it is one of the self::LENGTH_* keywords.
     * @return int|string
     */
    public function getCellSize()
    {
        
        if (is_null($this->cellsizeCache)) {
            if (!is_null($this->length)) {
                $key = $this->getDataType()->getName();
                    
                $annotations = $this->getDataTypeAnnotations();
                    
                if (!isset($annotations['Addiks\\\\Datatype'])) {
                    throw new ErrorException("Data-type '{$key}' without byte-length annotation requested! (All datatypes should have one)");
                }
                    
                /* @var $annotation \Addiks\Common\Annotation */
                $annotation = current($annotations['Addiks\\\\Datatype']);
                    
                $length = $annotation['bytelength'];
                    
                if (is_numeric($length)) {
                    $this->cellsizeCache = (int)$length;
                        
                } else {
                    switch($annotation['type']){
                
                        case 'length':
                            $this->cellsizeCache = $this->getLength()+$this->getSecondLength();
                                
                        case 'storage':
                            throw new ErrorException("Storage-length for data-cells is not implemented yet!");
                                
                        default:
                            throw new ErrorException("Invalid (non-implemented?) keyword '{$annotation['type']}' found for datatype '{$key}'!");
                    }
                }
            } else {
                $this->cellsizeCache = $this->getLength()+$this->getSecondLength();
            }
            
        }
        
        return $this->cellsizeCache;
    }
    
    public function isBinary()
    {
        
        $annotations = $this->getDataTypeAnnotations();
        
        return isset($annotations['binary']);
    }
    
    protected function getDataTypeAnnotations()
    {
        
        /* @var $dataType DataType */
        $dataType = $this->getDataType();
        
        $key = $dataType->getName();
        
        $reflection = new \ReflectionClass($dataType);
        
        /* @var $analyzer ClassAnalyzer */
        $analyzer   = ClassAnalyzer::getInstanceFor(get_class($dataType), $reflection->getFileName());
        
        $docComment  = $analyzer->getClassConstantDocComment($key);
        $annotations = DocComment::extractAnnotationsFromString($docComment);
        
        return $annotations;
    }
}

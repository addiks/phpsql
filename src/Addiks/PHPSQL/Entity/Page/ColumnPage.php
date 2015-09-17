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

use ErrorException;
use Addiks\PHPSQL\Value\Enum\Page\Column\DataType;
use Addiks\PHPSQL\BinaryConverterTrait;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;

/**
 * A page in an table-index containing information about a column in the table.
 *
 * name         128byte
 * index          2byte
 * datatype       2byte
 * extra          2byte
 * length        16byte
 * secondlength  16byte
 * default-val   64byte
 * fk_table       8byte
 * fk_column      4byte
 * reserved      16byte
 * ____________________
 *              256byte
 */
class ColumnPage
{
    
    use BinaryConverterTrait;
    
    const PAGE_SIZE = 256;
    
    private $id;

    public function setId($id)
    {
        $this->id = (string)$id;
    }

    public function getId()
    {
        return $this->id;
    }

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
     * The index of the column.
     * Determines the order of the columns.
     * (16 bit / 2byte)
     * @var int
     */
    private $index = 0;

    public function setIndex($index)
    {
        $this->index = (int)$index;
    }

    public function getIndex()
    {
        return $this->index;
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

    private $defaultValue;

    public function setDefaultValue($defaultValue)
    {
        $this->defaultValue = $defaultValue;
    }

    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    public function isDefaultValueInFile()
    {
        return ($this->getLength() + $this->getSecondLength()) > 64;
    }

    public function hasDefaultValue()
    {
        return !is_null($this->defaultValue);
    }
    
    ### PERSISTANCE
    
    public function setData($data)
    {
        
        if (strlen($data) !== self::PAGE_SIZE) {
            throw new \InvalidArgumentException("Invalid data-block given to column-page! (length ".strlen($data)." != ".self::PAGE_SIZE.")");
        }
        
        $rawName         = substr($data, 0, 128);
        $rawIndex        = substr($data, 128, 2);
        $rawDataType     = substr($data, 130, 2);
        $rawLength       = substr($data, 132, 16);
        $rawSecondLength = substr($data, 148, 16);
        $hasDefaultValue = substr($data, 164, 1);
        $rawDefaultValue = substr($data, 165, 63);
        $rawExtra        = substr($data, 228, 2);
        $rawFkTable      = substr($data, 230, 8);
        $rawFkColumn     = substr($data, 238, 4);
        
        $name         = rtrim($rawName, "\0");
        $length       = ltrim($rawLength, "\0");
        $secondLength = ltrim($rawSecondLength, "\0");
        $defaultValue = rtrim($rawDefaultValue, "\0");
        $fkTable      = ltrim($rawFkTable, "\0");
        $fkColumn     = ltrim($rawFkColumn, "\0");
        
        $dataType     = unpack("n", $rawDataType)[1];
        $index        = unpack("n", $rawIndex)[1];
        $length       = $this->strdec($length);
        $secondLength = $this->strdec($secondLength);
        $extra        = unpack("n", $rawExtra)[1];
        $fkTable      = $this->strdec($fkTable);
        $fkColumn     = $this->strdec($fkColumn);

        $hasDefaultValue = (bool)ord($hasDefaultValue);
        
        if (!$hasDefaultValue) {
            $defaultValue = null;
        }

        $this->setName($name);
        $this->setIndex($index);
        $this->setDataType(DataType::getByValue($dataType));
        $this->setLength($length);
        $this->setSecondLength($secondLength);
        $this->setDefaultValue($defaultValue);
        $this->setExtraFlags($extra);
        $this->setFKTableIndex($fkTable);
        $this->setFKColumnIndex($fkColumn);
    }
    
    public function getData()
    {
        
        $name          = $this->getName();
        $index         = $this->getIndex();
        $dataType      = $this->getDataType()->getValue();
        $length        = $this->getLength();
        $secondLength  = $this->getSecondLength();
        $defaultValue  = $this->getDefaultValue();
        $extra         = $this->getExtraFlags();
        $fkTableIndex  = $this->getFKTableIndex();
        $fkColumnIndex = $this->getFKColumnIndex();
        
        $rawDataType     = pack("n", $dataType);
        $rawIndex        = pack("n", $index);
        $rawLength       = $this->decstr($length);
        $rawSecondLength = $this->decstr($secondLength);
        $rawExtra        = pack("n", $extra);
        $rawFkTable      = $this->decstr($fkTableIndex);
        $rawFkColumn     = $this->decstr($fkColumnIndex);
        
        $rawName         = str_pad($name, 128, "\0", STR_PAD_RIGHT);
        $rawLength       = str_pad($rawLength, 16, "\0", STR_PAD_LEFT);
        $rawSecondLength = str_pad($rawSecondLength, 16, "\0", STR_PAD_LEFT);
        $rawDefaultValue = str_pad($defaultValue, 63, "\0", STR_PAD_RIGHT);
        $rawFkTable      = str_pad($rawFkTable, 8, "\0", STR_PAD_LEFT);
        $rawFkColumn     = str_pad($rawFkColumn, 4, "\0", STR_PAD_LEFT);
        
        $hasDefaultValue = chr($this->hasDefaultValue() ?0x01 :0x00);

        $data = $rawName.
                $rawIndex.
                $rawDataType.
                $rawLength.
                $rawSecondLength.
                $hasDefaultValue.
                $rawDefaultValue.
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
     * A cell-size keyword to indicate that the value should be stored in its own file.
     * @see FileResourceProxy
     * @var string
     */
    const LENGTH_FILE = "file";
    
    private $cellsizeCache = null;
    
    /**
     * Gets the size of a cell from this column in bytes.
     * If string, it is one of the self::LENGTH_* keywords.
     * @return int|string
     */
    public function getCellSize()
    {
        
        if (is_null($this->cellsizeCache)) {
            $dataType = $this->getDataType();

            if ($dataType->isInFile()) {
                throw new ErrorException("Size-getter for data-cells stored in file is not implemented yet!");
            }

            if (!is_null($this->length)) {
                $this->cellsizeCache = $this->getLength()+$this->getSecondLength();

            } else {
                $length = $dataType->getByteLength();
                    
                $this->cellsizeCache = (int)$length;
            }
        }
        
        return $this->cellsizeCache;
    }
    
    public function isBinary()
    {
        $dataType = $this->getDataType();

        return $dataType->isBinary();
    }
}

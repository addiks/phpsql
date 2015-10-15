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
use Addiks\PHPSQL\Value\Enum\Page\Index\ForeignKeyMethod;
use Addiks\PHPSQL\Value\Enum\Page\Index\IndexEngine;
use Addiks\PHPSQL\Value\Enum\Page\Index\Type;

/**
 * Holds information about an index for a table.
 */
class IndexSchema
{
    
    public function __construct()
    {
        $this->data = str_pad("", self::PAGE_SIZE, "\0");
    }

    /**
     *  name:       64byte
     *  columns:   128byte
     *  type:        1byte
     *  engine:      1byte
     *  fkoum:       1byte
     *  fkudm:       1byte
     *  keyLength:   4byte
     *  reserved:   57byte
     *            ________
     *             256byte
     * @var int
     */
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
     * Name of this index.
     * 64 bytes reserved.
     * @return string
     */
    public function getName()
    {
        $rawName = substr($this->data, 0, 64);
        
        return rtrim($rawName, "\0");
    }
    
    public function setName($name)
    {
        $rawName = str_pad($name, 64, "\0", STR_PAD_RIGHT);
        $this->data = $rawName.substr($this->data, 64);
        $this->selfTest();
    }
    
    /**
     * Array of column-schema-indexes.
     * @var array
     */
    public function getColumns()
    {
        $rawColumns = substr($this->data, 64, 128);
        $columnsString = rtrim($rawColumns, "\0");
        return explode("\0", $columnsString);
    }
    
    public function setColumns(array $columns)
    {
        
        foreach ($columns as $columnId) {
            if (!is_numeric($columnId)) {
                throw new ErrorException("Index-column-id '{$columnId}' is not numeric!");
            }
        }
        
        $rawColumns = str_pad(implode("\0", $columns), 128, "\0", STR_PAD_RIGHT);
        $this->data = substr($this->data, 0, 64).$rawColumns.substr($this->data, 192);
        $this->selfTest();
    }
    
    /**
     * Type of index.
     * E.g.: INDEX, UNIQUE, PRIMARY, ...
     *
     * @return Type
     */
    public function getType()
    {
        $rawType = $this->data[192];
        $type = ord($rawType);
        return Type::getByValue($type);
    }
    
    public function setType(Type $type)
    {
        $rawType = chr($type->getValue());
        $this->data = substr($this->data, 0, 192).$rawType.substr($this->data, 193);
        $this->selfTest();
    }
    
    public function isPrimary()
    {
        return $this->getType() === Type::PRIMARY();
    }
    
    public function isUnique()
    {
        return in_array($this->getType(), [
            Type::UNIQUE(),
            Type::PRIMARY()
        ]);
    }
    
    /**
     * Engine 'used' for index.
     * (Not really used, its just the information stored to increase compatibility with mysql.)
     *
     * @return IndexEngine
     */
    public function getEngine()
    {
        $rawEngine = $this->data[193];
        $engine = ord($rawEngine);

        if ($engine <= 0) {
            $engine = IndexEngine::BTREE;
        }

        return IndexEngine::getByValue($engine);
    }
    
    public function setEngine(IndexEngine $engine)
    {
        $rawEngine = chr($engine->getValue());
        $this->data = substr($this->data, 0, 193).$rawEngine.substr($this->data, 194);
        $this->selfTest();
    }
    
    public function getForeignKeyOnUpdateMethod()
    {
        $rawMethod = $this->data[194];
        $method = ord($rawMethod);
        return ForeignKeyMethod::factory($method);
    }
    
    public function setForeignKeyOnUpdateMethod(ForeignKeyMethod $method)
    {
        $rawMethod = chr($method->getValue());
        $this->data = substr($this->data, 0, 194).$rawMethod.substr($this->data, 195);
        $this->selfTest();
    }
    
    public function getForeignKeyOnDeleteMethod()
    {
        $rawMethod = $this->data[195];
        $method = ord($rawMethod);
        return ForeignKeyMethod::factory($method);
    }
    
    public function setForeignKeyOnDeleteMethod(ForeignKeyMethod $method)
    {
        $rawMethod = chr($method->getValue());
        $this->data = substr($this->data, 0, 195).$rawMethod.substr($this->data, 196);
        $this->selfTest();
    }
    
    public function getKeyLength()
    {
        $rawKeyLength = substr($this->data, 196, 4);
        
        $rawKeyLength = trim($rawKeyLength, "\0");
        
        // convert string to integer
        $keyLength = hexdec(implode("", array_map(function ($chr) {
            return str_pad(dechex(ord($chr)), 2, "0", STR_PAD_LEFT);
        }, str_split($rawKeyLength))));
        
        return $keyLength;
    }
    
    public function setKeyLength($keyLength)
    {
        
        // convert integer to string
        $string = implode("", array_map(function ($hex) {
            return chr(hexdec($hex));
        }, str_split(dechex((string)$keyLength), 2)));
        
        $string = str_pad($string, 4, "\0", STR_PAD_LEFT);
        $string = substr($string, 0, 4);
        
        $this->data = substr($this->data, 0, 196).$string.substr($this->data, 200);
        $this->selfTest();
    }
    
    private $data;
    
    public function setData($data)
    {
        if (!is_string($data)) {
            throw new ErrorException("Data for index-page has to be string!");
        }
        $this->data = $data;
        $this->selfTest();
    }
    
    public function getData()
    {
        return $this->data;
    }

    protected function selfTest()
    {
        if (strlen($this->data) !== self::PAGE_SIZE) {
            throw new ErrorException("Data for index-page has wrong length!");
        }
        $this->getEngine();
    }
}

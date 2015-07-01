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

namespace Addiks\PHPSQL\Entity\Page\Schema;

use Addiks\PHPSQL\Value\Enum\Page\Index\ForeignKeyMethod;

use Addiks\PHPSQL\Value\Enum\Page\Index\Engine;

use Addiks\PHPSQL\Value\Enum\Page\Index\Type;

use Addiks\Common\Entity;

use Addiks\Protocol\Entity\Exception\Error;

/**
 * Holds information about an index for a table.
 */
class Index extends Entity
{
    
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
    
    /**
     * Name of this index.
     * 64 bytes reserved.
     * @return string
     */
    public function getName()
    {
        $rawName = substr($this->getData(), 0, 64);
        
        return rtrim($rawName, "\0");
    }
    
    public function setName($name)
    {
        $rawName = str_pad($name, 64, "\0", STR_PAD_RIGHT);
        $this->setData($rawName.substr($this->getData(), 64));
    }
    
    /**
     * Array of column-schema-indexes.
     * @var array
     */
    public function getColumns()
    {
        $rawColumns = substr($this->getData(), 64, 128);
        $columnsString = rtrim($rawColumns, "\0");
        return explode("\0", $columnsString);
    }
    
    public function setColumns(array $columns)
    {
        
        foreach ($columns as $columnId) {
            if (!is_numeric($columnId)) {
                throw new Error("Index-column-id '{$columnId}' is not numeric!");
            }
        }
        
        $rawColumns = str_pad(implode("\0", $columns), 128, "\0", STR_PAD_RIGHT);
        $this->setData(substr($this->getData(), 0, 64).$rawColumns.substr($this->getData(), 192));
    }
    
    /**
     * Type of index.
     * E.g.: INDEX, UNIQUE, PRIMARY, ...
     *
     * @return Type
     */
    public function getType()
    {
        $rawType = $this->getData()[192];
        $type = ord($rawType);
        return Type::getByValue($type);
    }
    
    public function setType(Type $type)
    {
        $rawType = chr($type->getValue());
        $this->setData(substr($this->getData(), 0, 192).$rawType.substr($this->getData(), 193));
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
     * @return Engine
     */
    public function getEngine()
    {
        $rawEngine = $this->getData()[193];
        $engine = ord($rawEngine);
        return Engine::getByValue($engine);
    }
    
    public function setEngine(Engine $engine)
    {
        $rawEngine = chr($engine->getValue());
        $this->setData(substr($this->getData(), 0, 193).$rawEngine.substr($this->getData(), 194));
    }
    
    public function getForeignKeyOnUpdateMethod()
    {
        $rawMethod = $this->getData()[194];
        $method = ord($rawMethod);
        return ForeignKeyMethod::factory($method);
    }
    
    public function setForeignKeyOnUpdateMethod(ForeignKeyMethod $method)
    {
        $rawMethod = chr($method->getValue());
        $this->setData(substr($this->getData(), 0, 194).$rawMethod.substr($this->getData(), 195));
    }
    
    public function getForeignKeyOnDeleteMethod()
    {
        $rawMethod = $this->getData()[195];
        $method = ord($rawMethod);
        return ForeignKeyMethod::factory($method);
    }
    
    public function setForeignKeyOnDeleteMethod(ForeignKeyMethod $method)
    {
        $rawMethod = chr($method->getValue());
        $this->setData(substr($this->getData(), 0, 195).$rawMethod.substr($this->getData(), 196));
    }
    
    public function getKeyLength()
    {
        $rawKeyLength = substr($this->getData(), 196, 4);
        
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
        
        $this->setData(substr($this->getData(), 0, 196).$string.substr($this->getData(), 200));
    }
    
    private $data;
    
    public function setData($data)
    {
        if (!is_string($data)) {
            throw new Error("Data for index-page has to be string!");
        }
        if (strlen($data) !== self::PAGE_SIZE) {
            throw new Error("Data for index-page has wrong length!");
        }
        $this->data = $data;
    }
    
    public function getData()
    {
        if (is_null($this->data)) {
            $this->data = str_pad("", self::PAGE_SIZE, "\0");
        }
        return $this->data;
    }
}

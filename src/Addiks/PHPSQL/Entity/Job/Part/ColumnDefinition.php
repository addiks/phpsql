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

namespace Addiks\PHPSQL\Entity\Job\Part;

use Addiks\Common\Value\Text\Annotation;

use Addiks\Common\Tool\ClassAnalyzer;

use Addiks\Analyser\Service\TokenParser\CodeBlock\DocComment;

use Addiks\Protocol\Entity\Exception\Error;

use Addiks\PHPSQL\Value\Enum\Page\Column\DataType;

use Addiks\PHPSQL\Entity\Job\Part;

class ColumnDefinition extends Part
{
    
    private $name;
    
    public function setName($name)
    {
        
        $pattern = "^[a-zA-Z0-9_-]{1,128}$";
        if (!preg_match("/{$pattern}/is", $name)) {
            throw new \InvalidArgumentException("Invalid table name '{$name}' does not match pattern '{$pattern}'!");
        }
        
        $this->name = $name;
    }
    
    public function getName()
    {
        return $this->name;
    }
    
    private $dataType;
    
    public function setDataType(DataType $dataType)
    {
        $this->dataType = $dataType;
    }
    
    public function getDataType()
    {
        return $this->dataType;
    }
    
    public function getDataSize()
    {
        
        if (!is_null($this->getDataTypeLength())) {
            $length = $this->getDataTypeLength();
            
            if (!is_null($this->getDataTypeSecondLength())) {
                $length += $this->getDataTypeSecondLength() + 1;
            }
            
            return $length;
        }

        if (is_null($this->getDataType())) {
            return 0;
        }
        
        $reflection = new \ReflectionClass($this->getDataType());
        /* @var $analyzer ClassAnalyzer */
        $analyzer = ClassAnalyzer::getInstanceFor($reflection->getName(), $reflection->getFileName());
        
        $docComment  = $analyzer->getClassConstantDocComment($this->getDataType()->getName());
        $annotations = DocComment::extractAnnotationsFromString($docComment);
        
        if (!isset($annotations['Addiks\\\\Datatype'])) {
            throw new Error("Missing annotation 'Addiks\\Datatype' on data-type-constant '{$reflection->getName()}::{$this->getDataType()->getName()}'");
        }
        
        /* @var $annotation Annotation */
        $annotation = current($annotations['Addiks\\\\Datatype']);
        
        if (isset($annotation['type'])) {
            switch($annotation['type']){
                case 'enum':
                    $maximumLength = 0;
                    foreach ($this->getEnumValues() as $value) {
                        if (strlen($value) > $maximumLength) {
                            $maximumLength = strlen($value);
                        }
                    }
                    return $maximumLength;
                    
                case 'storage':
                    throw new Conflict("Unimplemented!");
            }
            
        } else {
            return (int)$annotation['length'];
        }
        
    }
    
    private $dataTypeLength;
    
    public function setDataTypeLength($length)
    {
        $this->dataTypeLength = (int)$length;
    }
    
    public function getDataTypeLength()
    {
        return $this->dataTypeLength;
    }
    
    /**
     * E.g. for number of numbers after decimal seperator ('.') in float.
     * @var int
     */
    private $dataTypeSecondLength;
    
    public function setDataTypeSecondLength($length)
    {
        $this->dataTypeSecondLength = (int)$length;
    }
    
    public function getDataTypeSecondLength()
    {
        return $this->dataTypeSecondLength;
    }
    
    private $enumValues = array();
    
    public function addEnumValue($value)
    {
        $this->enumValues[] = $value;
    }
    
    public function getEnumValues()
    {
        return $this->enumValues;
    }
    
    private $isAutoIncrement = false;
    
    public function setAutoIncrement($bool)
    {
        $this->isAutoIncrement = (bool)$bool;
    }
    
    public function getIsAutoIncrement()
    {
        return $this->isAutoIncrement;
    }
    
    private $nullable = true;
    
    public function setIsNullable($bool)
    {
        $this->nullable = (bool)$bool;
    }
    
    public function getIsNullable()
    {
        return $this->nullable;
    }
    
    public function getIsNotNullable()
    {
        return !$this->getIsNullable();
    }
    
    private $defaultValue;
    
    public function setDefaultValue($value)
    {
        $this->defaultValue = $value;
    }
    
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }
    
    private $isUnsigned = false;
    
    public function setIsUnsigned($bool)
    {
        $this->isUnsigned = (bool)$bool;
    }
    
    public function getIsUnsigned()
    {
        return $this->isUnsigned;
    }
    
    private $isPrimaryKey = false;
    
    public function setIsPrimaryKey($bool)
    {
        $this->isPrimaryKey = (bool)$bool;
    }
    
    public function getIsPrimaryKey()
    {
        return $this->isPrimaryKey;
    }
    
    private $isUnique = false;
    
    public function setIsUnique($bool)
    {
        $this->isUnique = (bool)$bool;
    }
    
    public function getIsUnique()
    {
        return $this->isUnique;
    }
    
    private $comment;
    
    public function setComment($comment)
    {
        $this->comment = $comment;
    }
    
    public function getComment()
    {
        return $this->comment;
    }
    
    private $onUpdate;
    
    public function setOnUpdate($onUpdate)
    {
        $this->onUpdate = $onUpdate;
    }
    
    public function getOnUpdate()
    {
        return $this->onUpdate;
    }
    
    private $onDelete;
    
    public function setOnDelete($onDelete)
    {
        $this->inDelete = $onDelete;
    }
    
    public function getOnDelete()
    {
        return $this->onDelete;
    }
    
    private $characterSet;
    
    public function setCharacterSet($charSet)
    {
        $this->characterSet = $charSet;
    }
    
    public function getCharacterSet()
    {
        return $this->characterSet;
    }
    
    private $collate;
    
    public function setCollate($collate)
    {
        $this->collate = $collate;
    }
    
    public function getCollate()
    {
        return $this->collate;
    }
}

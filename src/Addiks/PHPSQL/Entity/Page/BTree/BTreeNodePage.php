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

namespace Addiks\PHPSQL\Entity\Page\BTree;

use Addiks\PHPSQL\Iterators\CustomIterator;

/**
 * A node in an b-tree index.
 * @see http://en.wikipedia.org/wiki/B-tree
 */
class BTreeNodePage
{
    
    /**
     * The fork-rate is the number of keys/nodes in a index-node.
     * It is a parameter for how well the index performs in searching rows:
     *
     *      $readCountToFindRow = ceil(log($countOfRowsInTable, $this->forkRate-1));
     *
     * for example:
     *      Given there are 12.345.678.912 rows in the table,
     *      and the fork-rate is set to 128,
     *      it would take 5 (rounded up from 4,789...) nodes to be read from index to find a node.
     *
     * Value always has to be like n*2+1 (3,5,7,9,11,13,15,17, ...)
     *
     * @var int
     */
    private $forkRate = 33;
    
    public function setForkRate($forkRate)
    {
        $this->forkRate = (int)$forkRate;
    }
    
    public function getForkRate()
    {
        return $this->forkRate;
    }
    
    private $keyLength = 8;
    
    public function setKeyLength($length)
    {
        $this->keyLength = (int)$length;
    }
    
    public function getKeyLength()
    {
        return $this->keyLength;
    }
    
    /**
     * Every key gets:
     *      [KEYLENGTH] bytes for node-reference when value is lower,
     *      [KEYLENGTH] bytes for row-index and
     *      [KEYLENGTH] bytes for search-value comparisation.
     * Plus one key-length for storing the case the 'is-not-leaf' and searched value is bigger then last element.
     * @return int
     */
    public function getPageSize()
    {
        return ($this->forkRate * ($this->keyLength*3) ) + $this->keyLength;
    }
    
    private $data;
    
    public function setData($data)
    {
        if (strlen($data) !== $this->getPageSize()) {
            throw new ErrorException("Invalid data-page! (actual ".strlen($data)."; expected {$this->getPageSize()})");
        }
        $this->data = $data;
    }
    
    public function getData()
    {
        if (is_null($this->data)) {
            $this->setData(str_pad("", self::getPageSize(), "\0"));
        }
        return $this->data;
    }
    
    public function split()
    {
        
        $halfDataLength = ((int)floor($this->forkRate/2)) * ($this->keyLength*3);
        
        // get two copies of original data. First with first half, second with second half.
        $myData     = substr($this->data, 0, $halfDataLength);
        $middleData = substr($this->data, $halfDataLength, $this->keyLength*3);
        $copyData   = substr($this->data, $halfDataLength+($this->keyLength*3), $halfDataLength);
        
        // fill both up with null-bytes.
        $copyData = str_pad($copyData, $this->getPageSize(), "\0");
        $myData   = str_pad($myData, $this->getPageSize(), "\0");
        
        $middleReference = substr($middleData, 0, $this->keyLength);
        $middleValue     = substr($middleData, $this->keyLength, $this->keyLength);
        $middleRowId     = substr($middleData, $this->keyLength*2, $this->keyLength);
        $myLastReference = $this->getLastReference();
        
        $copyFirstReference = substr($copyData, 0, $this->keyLength);
        
        $copyNode = clone $this;
        $copyNode->setData($copyData);
        
        $this->setData($myData);
        
        $this    ->setLastReference($middleReference);
        $copyNode->setLastReference($myLastReference);
        
        return [$middleReference, $middleValue, $middleRowId, $copyNode];
    }
    
    /**
     * Merges this node with another node.
     * Copies all keys of $mergeNode into this node.
     * Puts $middleBlock between them, using the last-reference of this as reference.
     * Adopts the last-reference of $mergeNode.
     *
     * This does not sort the keys!
     * $mergeNode's first key must be higher then this-node's last key.
     * Always merge the node with bigger values into the one with smaller values,
     * never the other way around.
     *
     * @param Node $mergeNode
     * @param array $middleBlock
     * @throws \ErrorException
     */
    public function merge(Node $mergeNode, array $middleBlock = null)
    {
        if ($mergeNode->getKeyLength() !== $this->keyLength) {
            throw new ErrorException("Tried to merge nodes with different key-length!");
        }
        if (!is_null($middleBlock) && count($middleBlock)<3) {
            $dump = str_replace("\n", "", var_export($middleBlock, true));
            throw new ErrorException("Invalid middle-block given for merge! ({$dump})");
        }
        
        $myLastIndex    = $this     ->getLastWrittenIndex();
        $mergeLastIndex = $mergeNode->getLastWrittenIndex();
        
        if (($myLastIndex+$mergeLastIndex+2) > ($this->forkRate-1)) {
            $actual = ($myLastIndex+$mergeLastIndex+2);
            $expect = ($this->forkRate-1);
            throw new ErrorException("Cannot merge nodes because theyre sum is too big! ({$actual} > {$expect})");
        }
        
        if ((trim($this->getLastReference(), "\0")==='') !== (trim($mergeNode->getLastReference(), "\0")==='')) {
            throw new ErrorException("Cannot merge leaf-node and non-leaf-node!");
        }
        
        $mergeData = $mergeNode->getData();
        
        $newData  = substr($this->data, 0, ($myLastIndex+1)*$this->keyLength*3);
        if (!is_null($middleBlock)) {
            $newData .= $this->getLastReference().$middleBlock[1].$middleBlock[2];
        }
        $newData .= substr($mergeData, 0, ($mergeLastIndex+1)*$this->keyLength*3);
        $newData  = str_pad($newData, $this->forkRate*$this->keyLength*3, "\0", STR_PAD_RIGHT);
        $newData .= substr($mergeData, strlen($mergeData)-$this->keyLength);
        
        $this->setData($newData);
    }
    
    /**
     * When not in 'leaf-mode', all references get used when search-value
     * is lower then corresponding reference-value.
     * This leaves the case open that the search-value is bigger then all reference-values.
     * In that case, this extra reference is used.
     */
    public function getLastReference()
    {
        return substr($this->data, $this->forkRate * $this->keyLength*3, $this->keyLength);
    }
    
    public function setLastReference($reference)
    {
        
        $this->data = substr($this->data, 0, $this->forkRate * $this->keyLength*3).$reference;
    }
    
    public function getIterator()
    {
        
        $data = $this->data;
        $index = 0;
        $keyLength = $this->keyLength;
        $node = $this;
        
        return new CustomIterator(null, [
            'rewind' => function () use (&$index) {
                $index = 0;
            },
            'valid' => function () use (&$index) {
                return $index < $this->forkRate;
            },
            'key' => function () use (&$index, &$data, $keyLength) {
                return $index;
            },
            'current' => function () use (&$index, &$data, $node) {
                return $node->getIndexBlock($index);
            },
            'next' => function () use (&$index) {
                $index++;
            },
        ]);
    }
    
    public function isFull()
    {
        return strlen(trim(substr($this->data, ($this->forkRate-1)*$this->keyLength*3, $this->keyLength*3), "\0"))>0;
    }
    
    public function isEmpty()
    {
        return strlen(trim(substr($this->data, $this->keyLength, $this->keyLength), "\0"))<=0;
    }
    
    public function removeIndex($index)
    {
        
        $data = $this->data;
        
        $data = substr($data, 0, $index*$this->keyLength*3).
                substr($data, ($index+1)*$this->keyLength*3, (($this->forkRate-($index+1))*$this->keyLength*3)).
                str_pad("", $this->keyLength*3, "\0").
                substr($data, ($this->forkRate)*$this->keyLength*3, $this->keyLength);
        
        $this->setData($data);
    }
    
    public function add($value, $rowID, $reference = null)
    {
        
        if (is_null($reference)) {
            $reference = str_pad("", $this->keyLength, "\0");
        }
    #	$this->checkValue($reference);
    #	$this->checkValue($value);
    #	$this->checkValue($rowID);
        
        if ($reference[$this->keyLength-1] === chr(13) && defined("DEBUG")) {
            throw new ErrorException("WRITE HIT!");
        }
        
        ### FIND INSERT INDEX
        
        $index = 0;
        while (1) {
            $blockValue = substr($this->data, $index*$this->keyLength*3+$this->keyLength, $this->keyLength);
            if (ltrim($blockValue, "\0")==='') {
                break;
            }
            if ($value < $blockValue) {
                break;
            }
            if ($value === $blockValue) {
                return;
            }
            $index++;
        }
        
        ### PROCESS INSERT
        
        $data = $this->data;
        
        $data = substr($data, 0, $index*$this->keyLength*3).
                $reference.$value.$rowID.
                substr($data, $index*$this->keyLength*3, ($this->forkRate-$index-1)*$this->keyLength*3).
                substr($data, strlen($data)-$this->keyLength);
        
        $this->setData($data);
        
        return $index;
    }
    
    public function setIndexBlock($index, $reference, $value, $rowID)
    {
    #	$this->checkValue($reference);
    #	$this->checkValue($value);
    #	$this->checkValue($rowID);
        
        if ($reference[$this->keyLength-1] === chr(13) && defined("DEBUG")) {
            throw new ErrorException("WRITE HIT!");
        }
        
        if ($index >= $this->forkRate) {
            throw new ErrorException("Tried to write index out of node!");
        }
        
        $data = $this->data;
        
        $data = substr($data, 0, $index*$this->keyLength*3).
                $reference.$value.$rowID.
                substr($data, ($index+1)*$this->keyLength*3);
        
        $this->setData($data);
    }
    
    public function setIndexReference($index, $reference)
    {
    #	$this->checkValue($reference);
        
        if ($reference[$this->keyLength-1] === chr(13) && defined("DEBUG")) {
            throw new ErrorException("WRITE HIT!");
        }
        
        $data = $this->data;
        
        $data = substr($data, 0, $index*$this->keyLength*3).
                $reference.
                substr($data, ($index*$this->keyLength*3) +$this->keyLength);
        
        $this->setData($data);
    }
    
    public function setIndexValue($index, $value)
    {
    #	$this->checkValue($value);
        
        $data = $this->data;
        
        $data = substr($data, 0, $index*$this->keyLength*3+$this->keyLength).
                $value.
                substr($data, ($index*$this->keyLength*3) +$this->keyLength*2);
        
        $this->setData($data);
    }
    
    public function setIndexRowId($index, $rowId)
    {
    #	$this->checkValue($rowId);
        
        $data = $this->data;
        
        $data = substr($data, 0, $index*$this->keyLength*3+$this->keyLength*2).
                $rowId.
                substr($data, ($index*$this->keyLength*3) +$this->keyLength*3);
        
        $this->setData($data);
    }
    
    public function getIndexBlock($index)
    {
        
        $data = $this->data;
        
        return str_split(substr($data, $index*$this->keyLength*3, $this->keyLength*3), $this->keyLength);
    }
    
    public function getValueByIndex($index)
    {
        
        list($reference, $value, $rowId) = $this->getIndexBlock($index);
        
        return $value;
    }
    
    public function hasValueAtIndex($index)
    {
        if ($index >= $this->forkRate) {
            return false;
        }
        return trim(substr($this->data, ($index*$this->keyLength*3)+$this->keyLength, $this->keyLength), "\0")!=='';
    }
    
    public function getRowIdByIndex($index)
    {
        
        list($reference, $value, $rowId) = $this->getIndexBlock($index);
        
        return $rowId;
    }
    
    public function getLastWrittenIndex()
    {
        
        $index = 0;
        while (1) {
            $value = substr($this->data, $index*$this->keyLength*3+$this->keyLength, $this->keyLength);
            if (trim($value, "\0")==='') {
                return $index<=0 ?false :$index-1;
            }
            $index++;
        }
    }
    
    public function removeReference($reference)
    {
    #	$this->checkValue($reference);
        $index = $this->getIndexByReference($reference);
        $this->removeIndex($index);
    }
    
    public function removeValue($needle)
    {
    #	$this->checkValue($needle);
        $index = $this->getIndexByValue($needle);
        $this->removeIndex($index);
    }
    
    public function getIndexByValue($needle)
    {
    #	$this->checkValue($needle);
        $index = 0;
        while (1) {
            $value = substr($this->data, $index*$this->keyLength*3+$this->keyLength, $this->keyLength);
            if (trim($value, "\0")==='' || $index === $this->forkRate) {
                return null;
            }
            if ($needle === $value) {
                return $index;
            }
            $index++;
        }
    }
    
    public function getIndexByRowId($needle)
    {
    #	$this->checkValue($needle);
        $index = 0;
        while (1) {
            $rowId = substr($this->data, $index*$this->keyLength*3+$this->keyLength*2, $this->keyLength);
            if (trim($rowId, "\0")==='' || $index === $this->forkRate) {
                return null;
            }
            if ($needle === $rowId) {
                return $index;
            }
            $index++;
        }
    }
    
    /**
     * Gets the index of the key containing searched reference.
     * Returnes $this->forkRate if reference is in 'last-reference' of this node.
     * Returnes null if reference is not found in this node.
     *
     * @return int|null
     * @param string $needle
     */
    public function getIndexByReference($needle)
    {
    #	$this->checkValue($needle);
        if ($needle === $this->getLastReference()) {
            return $this->forkRate;
        }
        $index = 0;
        while (1) {
            $reference = substr($this->data, $index*$this->keyLength*3, $this->keyLength);
            if (trim($reference, "\0")==='' || $index === $this->forkRate) {
                return null;
            }
            if ($needle === $reference) {
                return $index;
            }
            $index++;
        }
    }
    
    public function getReferenceByValue($needle)
    {
    #	$this->checkValue($needle);
        
        foreach ($this->getIterator() as $block) {
            list($reference, $value, $rowId) = $block;
            
            if ($needle === $value) {
                return $key;
            }
        }
        
        return null;
    }
    
    public function getRowIdByValue($needle)
    {
    #	$this->checkValue($needle);
        
        foreach ($this->getIterator() as $block) {
            list($reference, $value, $rowId) = $block;
            if ($needle === $value) {
                return $rowId;
            }
        }
        
        return null;
    }
    
    public function getNearestIndexByValue($needle)
    {
    #	$this->checkValue($needle);
        
        $index = 0;
        while (1) {
            $value = substr($this->data, $index*$this->keyLength*3+$this->keyLength, $this->keyLength);
            if (trim($value, "\0")==='' || $index === $this->forkRate) {
                return null;
            }
            if ($needle < $value) {
                return $index;
            }
            $index++;
        }
    }
    
    public function getNearestReferenceByValue($needle)
    {
    #	$this->checkValue($needle);
        $reference = null;
        $index = $this->getNearestIndexByValue($needle);
        if (is_null($index)) {
            $reference = $this->getLastReference();
        } else {
            $reference = substr($this->data, $index*$this->keyLength*3, $this->keyLength);
        }
        return $reference;
    }
    
    public function getNearestIndexByRowId($needle)
    {
    #	$this->checkValue($needle);
        
        $index = 0;
        while (1) {
            $rowId = substr($this->data, $index*$this->keyLength*3+$this->keyLength*2, $this->keyLength);
            if (trim($rowId, "\0")==='' || $index === $this->forkRate) {
                return null;
            }
            if ($needle < $rowId) {
                return $index;
            }
            $index++;
        }
    }
    
    public function getNearestReferenceByRowId($needle)
    {
    #	$this->checkValue($needle);
        $index = $this->getNearestIndexByRowId($needle);
        return is_null($index) ?$this->getLastReference() :substr($this->data, $index*$this->keyLength*3, $this->keyLength);
    }
    
    public function getReferenceByIndex($index)
    {
        list($reference, $value, $rowId) = $this->getIndexBlock($index);
        
        return $reference;
    }
    
    private function checkValue($value)
    {
        if (!is_string($value) || strlen($value) !== $this->keyLength) {
            throw new ErrorException("Invalid value '{$value}'!");
        }
    }
}

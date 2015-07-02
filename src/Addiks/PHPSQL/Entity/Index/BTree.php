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

namespace Addiks\PHPSQL\Entity\Index;

use Addiks\PHPSQL\Entity;
use Addiks\PHPSQL\Tool\CustomIterator;
use Addiks\PHPSQL\Entity\Storage;
use Addiks\PHPSQL\Entity\Index\IndexInterface;
use Addiks\PHPSQL\Entity\Page\BTree\Node;
use Addiks\PHPSQL\Service\BinaryConverterTrait;

class BTree extends Entity implements \IteratorAggregate, IndexInterface
{
    
    use BinaryConverterTrait {
        BinaryConverterTrait::decstr as BCTdecstr;
        BinaryConverterTrait::strdec as BCTstrdec;
    }
    
    public function __construct(Storage $storage, $keyLength, $forkRate = 33)
    {
        
        if (!is_numeric($keyLength)) {
            throw new \ErrorException("Keylength has to specified as integer!");
        }
        $keyLength = (int)$keyLength;
        
        if ($keyLength < 4) {
            $keyLength = 4;
        }
        
        if (!is_numeric($forkRate)) {
            throw new \ErrorException("Forkrate has to specified as integer!");
        }
        $forkRate = (int)$forkRate;
        
        $this->storage = $storage;
        $this->setKeyLength($keyLength);
        $this->forkRate = $forkRate;
        
        // if storage is empty, initialize it
        if ($storage->getLength()<=1) {
            $storage->setData(str_pad("", $keyLength*8, "\0"));
            
            $rootNode = new Node();
            $rootNode->setKeyLength($keyLength);
            $rootNode->setForkRate($this->forkRate);
            
            // make sure root-reference is in index 1, not 0.
            // that way we can use reference 0 as 'not defined'/'not used'
            $this->setRootReference(1);
            $this->writeNode($rootNode, 1);
        }
        
    }
    
    ### PUBLIC ACTIONS
    
    public function search($needle)
    {
        
        if (is_int($needle)) {
            $needle = $this->decstr($needle, $this->getKeyLength());
        } elseif (strlen($needle)<$this->keyLength) {
            $needle = str_pad($needle, $this->keyLength, "\0", STR_PAD_LEFT);
        }
        
        if (is_null($this->getDoublesStorage())) {
            $result = $this->getRowIdByValue($needle);
            
            if (is_null($result)) {
                return array();
                
            } else {
                return [$result];
            }
        }
        
        $index  = $this->getRowIdByValue($needle);
        $handle = $this->getDoublesStorage()->getHandle();
        $result = array();
        
        if (!is_null($index)) {
            do {
                $index = $this->strdec($index);
                fseek($handle, ($this->keyLength*2)*$index, SEEK_SET);
                $rowId = fread($handle, $this->keyLength);
                $index = fread($handle, $this->keyLength);
                if (trim($rowId, "\0")!=='') {
                    $result[] = $rowId;
                }
            } while (trim($index, "\0")!=="");
        }
        
        return $result;
    }
    
    public function insert($value, $rowId)
    {
        
        if (is_int($value)) {
            $value = $this->decstr($value, $this->getKeyLength());
        } elseif (strlen($value)<$this->keyLength) {
            $value = str_pad($value, $this->keyLength, "\0", STR_PAD_LEFT);
        }
        
        if (is_int($rowId)) {
            $rowId = $this->decstr($rowId, $this->getKeyLength());
        } elseif (strlen($rowId)<$this->keyLength) {
            $rowId = str_pad($rowId, $this->keyLength, "\0", STR_PAD_LEFT);
        }
        
        if (is_null($this->getDoublesStorage())) {
            return $this->insertValue($value, $rowId);
        }
        
        if (in_array($rowId, $this->search($value))) {
            return;
        }
        
        $handle = $this->getDoublesStorage()->getHandle();
        
        ### APPEND TO CHAIN
        
        $index = $this->getRowIdByValue($value);
        
        
        if (is_null($index)) {
            fseek($handle, 0, SEEK_END);
            $index = ftell($handle) /($this->keyLength*2);
            fwrite($handle, str_pad("", $this->keyLength*2, "\0"));
            $this->insertValue($value, $this->decstr($index, $this->getKeyLength()));
        } else {
            $index = $this->strdec($index);
        }
        
        do {
            fseek($handle, ($index*($this->keyLength*2)), SEEK_SET);
            $checkRowId = fread($handle, $this->keyLength);
            if (trim($checkRowId, "\0")==="") {
                fseek($handle, 0-$this->keyLength, SEEK_CUR);
                fwrite($handle, $rowId);
                return;
            }
            $index = $this->strdec(fread($handle, $this->keyLength));
        } while ($index > 0);
        
        fseek($handle, 0-$this->keyLength, SEEK_CUR);
        $writeSeek = ftell($handle);
        
        ### WRITE NEW ROW-ID
        
        fseek($handle, 0, SEEK_END);
        $newIndex = ftell($handle) /($this->keyLength*2);
        
        $data  = $rowId;
        $data .= str_pad("", $this->keyLength, "\0");
        
        fwrite($handle, $data);
        
        fseek($handle, $writeSeek);
        fwrite($handle, $this->decstr($newIndex, $this->getKeyLength()));
    }
    
    public function remove($value, $rowId)
    {
        
        if (is_int($value)) {
            $value = $this->decstr($value, $this->getKeyLength());
        } elseif (strlen($value)<$this->keyLength) {
            $value = str_pad($value, $this->keyLength, "\0", STR_PAD_LEFT);
        }
        
        if (is_int($rowId)) {
            $rowId = $this->decstr($rowId, $this->getKeyLength());
        } elseif (strlen($rowId)<$this->keyLength) {
            $rowId = str_pad($rowId, $this->keyLength, "\0", STR_PAD_LEFT);
        }
        
        if (is_null($this->getDoublesStorage())) {
            return $this->removeRowId($rowId);
        }
        
        $index  = $this->getRowIdByValue($value);
        $handle = $this->getDoublesStorage()->getHandle();
        
        do {
            $index = $this->strdec($index);
            fseek($handle, $index*($this->keyLength*2), SEEK_SET);
            $checkRowId = fread($handle, $this->keyLength);
            if ($rowId === $checkRowId) {
                fseek($handle, 0-$this->keyLength, SEEK_CUR);
                fwrite($handle, str_pad("", $this->keyLength, "\0"));
                return;
            }
            $index = fread($handle, $this->keyLength);
        } while (trim($index, "\0")!=='');
    }
    
    ### INTERNAL ACTIONS
    
    protected function getRowIdByValue($needle)
    {
    
        /* @var $node Node */
        $node = $this->getRootNode();
    
        do {
            $rowId = $node->getRowIdByValue($needle);
    
            if (!is_null($rowId)) {
                return $rowId;
            }
    
            $reference = $node->getNearestReferenceByValue($needle);
                
            if (trim($reference, "\0")==='') {
                return null;
            }
    
            $node = $this->getNode($reference);
                
        } while (!is_null($node));
    
    }
    
    protected function insertValue($value, $rowId)
    {
    
        /* Contains all nodes from root-node to insert-node @var array */
        $nodePath     = [$this->getRootNode()];
        $nodePathKeys = [$this->getRootReference()];
    
        $insertNode      = null;
        $insertNodeIndex = null;
    
        ### FIND INSERT-NODE
    
        do {
            end($nodePath);
            end($nodePathKeys);
            
            /* @var $node Node */
            $node      = current($nodePath);
            $nodeIndex = current($nodePathKeys);
            
            if (!is_null($node->getIndexByValue($value))) {
                return;
            }
            
            if (!$node->isEmpty()) {
                $reference = $node->getNearestReferenceByValue($value);
        
                if (ltrim($reference, "\0")!=='') {
                    $node = $this->getNode($reference);
                    
                    if (!is_object($node)) {
                        throw new Error("Invalid reference '{$this->strdec($reference)}'!");
                    }
                    
                    $nodePath[]     = $node;
                    $nodePathKeys[] = $reference;
                    continue;
                }
            }
            
            $insertNodeIndex = $nodeIndex;
            $insertNode      = $node;
                
        } while (is_null($insertNode));
    
        ### PERFORM ADD
    
        // check if value is already in node
        if (is_int($insertNode->getIndexByValue($value))) {
            return;
        }
        
        $insertNode->add($value, $rowId);
        $this->writeNode($insertNode, $insertNodeIndex);
    
        ### RESOLVE
        
        $nodePath     = array_reverse($nodePath, true);
        $nodePathKeys = array_reverse($nodePathKeys, true);
        reset($nodePath);
        reset($nodePathKeys);
        $nodeIndex  = current($nodePathKeys);
        
        $this->resolveAfterInsert($nodePath, $nodePathKeys);
        
        if ($this->getIsDevelopmentMode()) {
            $this->selfTest();
        }
    }
    
    protected function resolveAfterInsert(&$nodePath, &$nodePathKeys)
    {
        
        ### GET NODE AND PARENT NODE
        
        $nodeIndex  = current($nodePathKeys);
        $node       = current($nodePath);
        next($nodePathKeys);
        next($nodePath);
        $parentIndex = current($nodePathKeys);
        $parentNode  = current($nodePath);
        
        if (!$node->isFull()) {
            return;
        }
        
        // if we try to split the root-node, create a new root-node
        if ($parentNode===false) {
            $parentNode = new Node();
            $parentNode->setKeyLength($this->getKeyLength());
            $parentNode->setForkRate($this->forkRate);
            $parentIndex = $this->writeNode($parentNode);
            $parentIndex = $this->decstr($parentIndex, $this->getKeyLength());
            $this->setRootReference($parentIndex);
            
            $nodePath     = array_reverse($nodePath, true);
            $nodePathKeys = array_reverse($nodePathKeys, true);
            $nodePath[]     = $parentNode;
            $nodePathKeys[] = $parentIndex;
            $nodePath     = array_reverse($nodePath, true);
            $nodePathKeys = array_reverse($nodePathKeys, true);
        }
        
        ### SPLIT NODE
        
        /* @var $splitNode Node */
        list($middleReference, $middleValue, $middleRowId, $splitNode) = $node->split();
        
        $this->writeNode($node, $nodeIndex);
        $splitNodeIndex = $this->writeNode($splitNode);
        $splitNodeIndex = $this->decstr($splitNodeIndex, $this->getKeyLength());
        
        ### ADD MIDDLE BLOCK TO PARENT NODE
        
        // update pointer to lower node, now points to higher node
        $parentSelfIndex = $parentNode->getIndexByReference($nodeIndex);
        if (is_null($parentSelfIndex)) {
            // when new root = current root is empty
            $parentNode->setLastReference($splitNodeIndex);
        } else {
            $parentNode->setIndexReference($parentSelfIndex, $splitNodeIndex);
        }
        $this->writeNode($parentNode, $parentIndex);
        
        // add middle value to parent node, points to lower node
        $parentAddIndex = $parentNode->add($middleValue, $middleRowId, $nodeIndex);
        $this->writeNode($parentNode, $parentIndex);
        
        if ($this->getIsDevelopMentMode()) {
            $this->selfTest();
        }
        
        $this->resolveAfterInsert($nodePath, $nodePathKeys);
    }
    
    protected function removeRowId($rowId)
    {
        
        if ($this->getRootNode()->isEmpty()) {
            return;
        }
        
        /* Contains all nodes from root-node to remove-node @var array */
        $nodePath     = [$this->getRootNode()];
        $nodePathKeys = [$this->getRootReference()];
        
        $removeNode      = null;
        $removeNodeIndex = null;
        
        ### FIND REMOVE-NODE
        
        while (1) {
            /* @var $node Node */
            $node      = end($nodePath);
            $nodeIndex = end($nodePathKeys);
            
            if (trim($node->getIndexByRowId($rowId), "\0")!=='') {
                $removeNodeIndex = $nodeIndex;
                $removeNode      = $node;
                break;
            }
            
            $reference = $node->getNearestReferenceByRowId($rowId);
            
            if (ltrim($reference, "\0")==='') {
                return; // row-id not found
            }
            
            $node = $this->getNode($reference);
            
            if (!is_object($node)) {
                throw new Error("Invalid reference '{$this->strdec($reference)}'!");
            }
            
            $nodePath[]     = $node;
            $nodePathKeys[] = $reference;
        }
        
        ### PERFORM REMOVAL
        
        $removeIndex     = $removeNode->getIndexByRowId($rowId);
        $removeReference = $removeNode->getReferenceByIndex($removeIndex);
        
        if (trim($removeReference, "\0")!=='') {
            // delete from inner node
            
            ### GET LEFT SYMMETRIC CHILD
            
            /* @var $leftChildNode Node */
            $leftChildIndex = $removeReference;
            $leftChildNode = $this->getNode($leftChildIndex);
            $leftSymmetricChildIndex = $leftChildIndex;
            $leftSymmetricChildNode  = $leftChildNode;
            $leftNodePath = $nodePath;
            $leftNodePathKeys = $nodePathKeys;
            end($leftNodePath);
            end($nodePathKeys);
            $leftNodePath[]     = $leftChildNode;
            $leftNodePathKeys[] = $leftChildIndex;
            while (trim($leftSymmetricChildNode->getLastReference(), "\0")!=="") {
                $leftSymmetricChildIndex = $leftSymmetricChildNode->getLastReference();
                $leftSymmetricChildNode = $this->getNode($leftSymmetricChildIndex);
                $leftNodePath[]     = $leftSymmetricChildNode;
                $leftNodePathKeys[] = $leftSymmetricChildIndex;
            }
            $leftChildLastIndex = $leftSymmetricChildNode->getLastWrittenIndex();
            
            ### GET RIGHT SYMMETRIC CHILD
            
            $nextReference = $removeNode->getReferenceByIndex($removeIndex+1);
            if (trim($nextReference, "\0")==='') {
                $nextReference = $removeNode->getLastReference();
            }
            $rightChildIndex = $nextReference;
            $rightChildNode = $this->getNode($rightChildIndex);
            $rightSymmetricChildIndex = $rightChildIndex;
            $rightSymmetricChildNode  = $rightChildNode;
            $rightNodePath = $nodePath;
            $rightNodePathKeys = $nodePathKeys;
            end($rightNodePath);
            end($rightNodePathKeys);
            $rightNodePath[]     = $rightChildNode;
            $rightNodePathKeys[] = $rightChildIndex;
            while (trim($rightSymmetricChildNode->getReferenceByIndex(0), "\0")!=="") {
                $rightSymmetricChildIndex = $rightSymmetricChildNode->getReferenceByIndex(0);
                $rightSymmetricChildNode = $this->getNode($rightSymmetricChildIndex);
                $rightNodePath[]     = $rightSymmetricChildNode;
                $rightNodePathKeys[] = $rightSymmetricChildIndex;
            }
            $rightChildLastIndex = $rightSymmetricChildNode->getLastWrittenIndex();
            
            if ($leftChildLastIndex >= ($this->forkRate-1)/2) {
                // get replacement-value from left symmetric child's last key
                $removeNode->setIndexValue($removeIndex, $leftSymmetricChildNode->getValueByIndex($leftChildLastIndex));
                $removeNode->setIndexRowId($removeIndex, $leftSymmetricChildNode->getRowIdByIndex($leftChildLastIndex));
                $leftSymmetricChildNode->removeIndex($leftChildLastIndex);
                $this->writeNode($removeNode, $removeNodeIndex);
                $this->writeNode($leftSymmetricChildNode, $leftSymmetricChildIndex);
                
            } else {
                // try right symmetric key
                // get replacement-value from right symmetric child's first key
                $removeNode->setIndexValue($removeIndex, $rightSymmetricChildNode->getValueByIndex(0));
                $removeNode->setIndexRowId($removeIndex, $rightSymmetricChildNode->getRowIdByIndex(0));
                $rightSymmetricChildNode->removeIndex(0);
                $this->writeNode($removeNode, $removeNodeIndex);
                $this->writeNode($rightSymmetricChildNode, $rightSymmetricChildIndex);
                
                if (!in_array($rightSymmetricChildIndex, $rightNodePathKeys)) {
                    $rightNodePath[]     = $rightSymmetricChildNode;
                    $rightNodePathKeys[] = $rightSymmetricChildIndex;
                }
                
                end($rightNodePath);
                end($rightNodePathKeys);
                $this->resolveAfterRemove($rightSymmetricChildIndex, 0, $rightNodePath, $rightNodePathKeys);
                
            }
            
        } else {
            // delete from leaf (outer node)
            
            $removeNode->removeIndex($removeIndex);
            $this->writeNode($removeNode, $removeNodeIndex);
            
            $this->resolveAfterRemove($removeNodeIndex, $removeIndex, $nodePath, $nodePathKeys);
        }
        
        if ($this->getIsDevelopmentMode()) {
            $this->selfTest();
        }
    }
    
    /**
     * Rebalances b-tree when node got too less keys.
     * @param string|int $nodeIndex
     * @param array $nodePath
     */
    protected function resolveAfterRemove($nodeIndex, $removeIndex, array $nodePath, array $nodePathKeys)
    {
        
        if (!is_string($nodeIndex)) {
            $nodeIndex = $this->decstr($nodeIndex, $this->getKeyLength());
        }
        
        while (current($nodePathKeys) !== $nodeIndex) {
            if (is_null(key($nodePathKeys))) {
                $indexEntity = $this;
                $keyString = implode(', ', array_keys($nodePath));
                throw new Error("Node-index to balance '{$this->strdec($nodeIndex)}' not in given node-path as key! ({$keyString})");
            }
            prev($nodePath);
            prev($nodePathKeys);
        }
        
        /* @var $node Node */
        $node = $this->getNode($nodeIndex);
        
        if ($node->getLastWrittenIndex()+1 >= ($this->forkRate-1)/2) {
            return; // no cleanup needed
        }
        
        if ($nodeIndex === $this->getRootReference()
        || $this->strdec($nodeIndex) === $this->getRootReference()) {
            return; // no cleanup for root node
        }
        
        prev($nodePathKeys);
        $parentIndex = current($nodePathKeys);
        next($nodePathKeys);
        
        $parentNode      = $this->getNode($parentIndex);
        $parentIndexToMe = $parentNode->getIndexByReference($nodeIndex);
        
        $leftSibblingIndex  = null;
        $leftSibblingNode   = null;
        $rightSibblingIndex = null;
        $rightSibblingNode  = null;
        $usedSibbling       = null;
        $usedSibblingIndex  = null;
        $getLastKey         = null;
            
        // try to use left-sibbling
        if ($parentIndexToMe>0) {
            if ($parentIndexToMe === $this->forkRate) {
                $leftSibblingIndex = $parentNode->getReferenceByIndex($parentNode->getLastWrittenIndex());
            } else {
                $leftSibblingIndex = $parentNode->getReferenceByIndex($parentIndexToMe-1);
            }
            $leftSibblingNode = $this->getNode($leftSibblingIndex);
            $leftSibblingLastIndex = $leftSibblingNode->getLastWrittenIndex();
            if ($leftSibblingLastIndex >= ($this->forkRate-1)/2) {
                $usedSibbling      = $leftSibblingNode;
                $usedSibblingIndex = $leftSibblingIndex;
                $getLastKey        = true;
            }
        }
        
        // if no usable left-sibbing found, try right sibbling
        if (is_null($getLastKey) && $parentIndexToMe<($this->forkRate)) {
            $rightSibblingIndex = $parentNode->getReferenceByIndex($parentIndexToMe+1);
            if (trim($rightSibblingIndex, "\0")==='') {
                $rightSibblingIndex = $parentNode->getLastReference();
            }
            $rightSibblingNode = $this->getNode($rightSibblingIndex);
            $rightSibblingLastIndex = $rightSibblingNode->getLastWrittenIndex();
            if ($rightSibblingLastIndex >= ($this->forkRate-1)/2) {
                $usedSibbling      = $rightSibblingNode;
                $usedSibblingIndex = $rightSibblingIndex;
                $getLastKey        = false;
            }
        }
        
        if (!is_null($usedSibbling)) {
            // move value from sibbling (rotate through parent)
            
            if ($parentIndexToMe === $this->forkRate) {
                $parentIndexToMe = $parentNode->getLastWrittenIndex();
                $parentIndexToMeIsLast = true;
            } else {
                $parentIndexToMeIsLast = false;
            }
            
            $lastKeyIndex = $usedSibbling->getLastWrittenIndex();
            if ($getLastKey) {
                $addReference = $usedSibbling->getLastReference();
                $usedSibbling->setLastReference($usedSibbling->getReferenceByIndex($lastKeyIndex));
                if ($parentIndexToMe>0 && !$parentIndexToMeIsLast) {
                    $parentIndexToMe--;
                }
            } else {
                $addReference = $usedSibbling->getReferenceByIndex(0);
            }
            $debugValue = $parentNode->getValueByIndex($parentIndexToMe);
            $addIndex = $node->add($debugValue, $parentNode->getRowIdByIndex($parentIndexToMe), $addReference);
            $parentNode->setIndexValue($parentIndexToMe, $usedSibbling->getValueByIndex($getLastKey ?$lastKeyIndex :0));
            $parentNode->setIndexRowId($parentIndexToMe, $usedSibbling->getValueByIndex($getLastKey ?$lastKeyIndex :0));
            $usedSibbling->removeIndex($getLastKey ?$lastKeyIndex :0);
            $this->writeNode($usedSibbling, $usedSibblingIndex);
            $this->writeNode($node, $nodeIndex);
            $this->writeNode($parentNode, $parentIndex);
            
            // check if references of remove node are in wrong order. if so, correct it.
            if ($this->strdec($addReference) > 0 && $this->getNode($addReference)->getValueByIndex(0) > $debugValue) {
                if ($addIndex === $node->getLastWrittenIndex()) {
                    $lastReference = $node->getLastReference();
                    $node->setLastReference($addReference);
                    $node->setIndexReference($addIndex, $lastReference);
                } else {
                    $nextReference = $node->getReferenceByIndex($addReference+1);
                    $node->setIndexReference($addReference+1, $node->getReferenceByIndex($addReference));
                    $node->setIndexReference($addReference, $nextReference);
                }
                $this->writeNode($node, $nodeIndex);
            }
            
            $newNodePath     = $nodePath;
            $newNodePathKeys = $nodePathKeys;
            array_pop($newNodePath);
            array_pop($newNodePathKeys);
            $newNodePath[]     = $usedSibbling;
            $newNodePathKeys[] = $usedSibblingIndex;
            end($newNodePath);
            end($newNodePathKeys);
            $this->resolveAfterRemove($usedSibblingIndex, $getLastKey ?$lastKeyIndex :0, $newNodePath, $newNodePathKeys);
        
        } elseif (!is_null($leftSibblingNode)) {
            // merge with left sibbling
            
            if ($parentIndexToMe > ($this->forkRate-1)) {
                $mergeParentMiddleIndex = $parentNode->getLastWrittenIndex();
                $leftSibblingNode->merge($node, $parentNode->getIndexBlock($mergeParentMiddleIndex));
                $parentNode->setLastReference($leftSibblingIndex);
                $parentNode->removeIndex($mergeParentMiddleIndex);
                $removeIndex = $mergeParentMiddleIndex;
                    
            } else {
                $leftSibblingNode->merge($node, $parentNode->getIndexBlock($parentIndexToMe-1));
                $parentNode->setIndexReference($parentIndexToMe, $leftSibblingIndex);
                $parentNode->removeIndex($parentIndexToMe-1);
                $removeIndex = $parentIndexToMe;
            }
        
            $this->writeNode($leftSibblingNode, $leftSibblingIndex);
            $this->writeNode($parentNode, $parentIndex);
            $this->deleteNode($nodeIndex);
            
            if ($this->getRootReference() === $parentIndex && $parentNode->isEmpty()) {
                // if parent is root and empty, replace it
                $this->deleteNode($this->getRootReference());
                $this->setRootReference($leftSibblingIndex);
                
                if ($this->getIsDevelopMentMode()) {
                    $this->selfTest();
                }
                    
            } elseif ($parentNode->getLastWrittenIndex()+1 <= ($this->forkRate-1)/2) {
                if ($this->getIsDevelopMentMode()) {
                    $this->selfTest();
                }
                    
                $this->resolveAfterRemove($parentIndex, $removeIndex, $nodePath, $nodePathKeys);
            }
        
        } elseif (!is_null($rightSibblingNode)) {
            // merge with right sibbling
            
            if ($parentIndexToMe > ($this->forkRate-1)) {
                $mergeParentMiddleIndex = $parentNode->getLastWrittenIndex();
                $node->merge($rightSibblingNode, $parentNode->getIndexBlock($mergeParentMiddleIndex));
                $parentNode->setLastReference($rightSibblingIndex);
                $parentNode->removeIndex($mergeParentMiddleIndex);
                $removeIndex = $mergeParentMiddleIndex;
                    
            } else {
                $node->merge($rightSibblingNode, $parentNode->getIndexBlock($parentIndexToMe));
                $parentNode->setIndexReference($parentIndexToMe+1, $nodeIndex);
                $parentNode->removeIndex($parentIndexToMe);
                $removeIndex = $parentIndexToMe;
            }
        
            $this->writeNode($parentNode, $parentIndex);
            $this->writeNode($node, $nodeIndex);
            $this->deleteNode($rightSibblingIndex);
        
            if ($this->getRootReference() === $parentIndex && $parentNode->isEmpty()) {
                // if parent is root and empty, replace it
                $this->deleteNode($this->getRootReference());
                $this->setRootReference($nodeIndex);
        
            } elseif ($parentNode->getLastWrittenIndex()+1 <= ($this->forkRate-1)/2) {
                $this->resolveAfterRemove($parentIndex, $removeIndex, $nodePath, $nodePathKeys);
            }
        
        } else {
            throw new Error("Empty node but no left or right sibbling to merge with! (should not happen)");
        }
        
    }
    
    ### HELPER
    
    private $storage;
    
    /**
     *
     * @return Storage
     */
    public function getStorage()
    {
        return $this->storage;
    }
    
    private $doublesStorage;
    
    public function getDoublesStorage()
    {
        return $this->doublesStorage;
    }
    
    public function setDoublesStorage(Storage $storage)
    {
        $this->doublesStorage = $storage;
        
        if ($storage->getLength()<=0) {
            $storage->setData(str_pad("", $this->keyLength*2, "\0"));
        }
    }
    
    private $forkRate = 33;
    
    public function getForkRate()
    {
        return $this->forkRate;
    }
    
    private $keyLength;
    
    public function getKeyLength()
    {
        return $this->keyLength;
    }
    
    protected function setKeyLength($length)
    {
        $length = $length<1 ?1 :$length;
        $this->keyLength = (int)$length;
    }
    
    public function getNodeIterator()
    {
        
        $keyLength = $this->getKeyLength();
        $handle = $this->getStorage()->getHandle();
        
        $node = new Node();
        $node->setKeyLength($keyLength);
        $node->setForkRate($this->forkRate);
        
        return new CustomIterator(null, [
            'rewind' => function () use ($handle, $keyLength) {
                fseek($handle, $keyLength*2, SEEK_SET);
            },
            'valid' => function () use ($handle, $node) {
                $data = fread($handle, $node->getPageSize());
                fseek($handle, 0-strlen($data), SEEK_CUR);
                return strlen($data) === $node->getPageSize();
            },
            'key' => function () use ($handle, $node) {
                return (int)((ftell($handle) -$node->getKeyLength()) / $node->getPageSize());
            },
            'current' => function () use ($handle, $node) {
                $data = fread($handle, $node->getPageSize());
                fseek($handle, 0-strlen($data), SEEK_CUR);
                
                if (strlen($data)!==$node->getPageSize()) {
                    return null;
                }
                
                $node->setData($data);
                return $node;
            },
            'next' => function () use ($handle, $node) {
                fseek($handle, $node->getPageSize(), SEEK_CUR);
            }
        ]);
    }
    
    protected function getNode($index)
    {
        
        if (is_string($index)) {
            $index = $this->strdec($index);
        }
        
        if ($index === 0) {
            throw new Error("Cannot get node with index 0!");
        }
        
        $keyLength = $this->getKeyLength();
        $handle = $this->getStorage()->getHandle();
        $seekBefore = ftell($handle);
        
        $node = new Node();
        $node->setKeyLength($keyLength);
        $node->setForkRate($this->forkRate);
        
        fseek($handle, ($keyLength*2) +($node->getPageSize()*$index), SEEK_SET);
        
        $data = fread($handle, $node->getPageSize());
        
        if (strlen($data)<=0) {
            throw new Error("Error reading node {$index}!");
            
        } elseif (strlen($data) !== $node->getPageSize()) {
            throw new Error("Error reading node {$index}, size ".strlen($data)." !== ".$node->getPageSize()."!");
        }
        
        $node->setData($data);
        
        fseek($handle, $seekBefore, SEEK_SET);
        return $node;
    }
    
    protected function writeNode(Node $node, $index = null)
    {
        
        if ($node->getKeyLength() !== $this->getKeyLength()) {
            throw new Error("Mismatching key-length ({$node->getKeyLength()} != {$this->getKeyLength()}) when wrinting node into index!");
        }
        
        if (is_null($index)) {
            if ($this->hasGarbage()) {
                $index = $this->popGarbage();
            } else {
                $index = $this->getNodeCount();
            }
        }
        
        if (is_string($index)) {
            $index = $this->strdec($index);
        }
        
        if ($index === 0) {
            throw new Error("Cannot write into index {$index}!");
        }
        
        $keyLength = $this->getKeyLength();
        $handle = $this->getStorage()->getHandle();
        
        fseek($handle, ($keyLength*2) +($node->getPageSize()*$index), SEEK_SET);
        
        fwrite($handle, $node->getData());
        
        return $index;
    }
    
    protected function deleteNode($index)
    {
        $handle = $this->getStorage()->getHandle();
        $node = new Node();
        $node->setKeyLength($this->getKeyLength());
        $node->setForkRate($this->forkRate);
        
        if (is_string($index)) {
            $index = $this->strdec($index);
        }
        
        $nodeCount = $this->getNodeCount();
        $keyLength = $this->getKeyLength();
        
        if ($index === $nodeCount-1) {
            fseek($handle, 0, SEEK_END);
            ftruncate($handle, ($keyLength*2) +($node->getPageSize()*($index)));
            
        } else {
            fseek($handle, ($keyLength*2) +($node->getPageSize()*$index), SEEK_SET);
            fwrite($handle, str_pad("", $node->getPageSize(), "\0"));
            
            $leftOverData = fread($handle, $keyLength +(($nodeCount-1)-$index)*$node->getPageSize());
            if (trim($leftOverData, "\0")==='') {
                ftruncate($handle, ($keyLength*2) +$node->getPageSize()*($index+1));
            } else {
                $this->pushGarbage($index);
            }
        }
        
    }
    
    protected function getNodeCount()
    {
        
        $keyLength = $this->getKeyLength();
        $handle = $this->getStorage()->getHandle();
        $beforeSeek = ftell($handle);
        
        $node = new Node();
        $node->setKeyLength($keyLength);
        $node->setForkRate($this->forkRate);
        
        fseek($handle, 0, SEEK_END);
        
        $count = (ftell($handle) -($keyLength*2)) / $node->getPageSize();
        fseek($handle, $beforeSeek);
        return $count;
    }
    
    protected function getRootReference()
    {
    
        $handle = $this->getStorage()->getHandle();
        fseek($handle, 0, SEEK_SET);
    
        $reference = fread($handle, $this->getKeyLength());
    
        if (strlen($reference) === 0 || trim($reference, "\0") === '') {
            fwrite($handle, str_pad(chr(1), $this->getKeyLength(), "\0"));
            fseek($handle, 0, SEEK_SET);
            $reference = fread($handle, $this->getKeyLength());
        }
    
        return $reference;
    }
    
    protected function setRootReference($reference)
    {
    
        if (!is_string($reference)) {
            $reference = $this->decstr($reference, $this->getKeyLength());
        }
        
        if (trim($reference, "\0")==="") {
            throw new Error("Root-reference cannot be int(0)!");
        }
    
        $handle = $this->getStorage()->getHandle();
        fseek($handle, 0, SEEK_SET);
        
        fwrite($handle, $reference);
    }
    
    /**
     * @return Node
     */
    protected function getRootNode()
    {
        if (is_null($node = $this->getNode($this->getRootReference()))) {
            $node = new Node();
            $node->setKeyLength($this->getKeyLength());
            $node->setForkRate($this->forkRate);
            $this->setRootReference($this->writeNode($node));
        }
        return $node;
    }
    
    ### HELPER
    
    /**
     * Converts any scalar value into a value usable in index.
     * (correct length, integers in binry-form)
     * @param scalar $value
     * @return string
     */
    public function decstr($value, $keyLength)
    {
        
        // check if reference can be stored in key-space
        if ($value<=0 || ceil(log($value, 256))>$this->getKeyLength()) {
            throw new Error("Invalid value given!");
        }
        
        return $this->BCTdecstr($value, $keyLength);
    }
    
    ### GARBAGE-STACK
    
    protected function pushGarbage($nodeIndex)
    {
        $node = new Node();
        $node->setKeyLength($this->getKeyLength());
        $node->setForkRate($this->forkRate);
        
        $handle = $this->getStorage()->getHandle();
        $seekBefore = ftell($handle);
        
        fseek($handle, ($this->keyLength*2)+($this->getGarbageReference()*$node->getPageSize()));
        
        while (true) {
            for ($i=0; $i<($this->forkRate*3); $i++) {
                $deletedReference = fread($handle, $this->keyLength);
                if (trim($deletedReference, "\0")==="") {
                    fseek($handle, 0-$this->keyLength, SEEK_CUR);
                    break 2;
                }
            }
            
            $nextGarbageIndex = fread($handle, $this->keyLength); // normally this is the last-index of a page
            
            if (trim($nextGarbageIndex, "\0")==='') {
                $writeSeek = ftell($handle) -$this->keyLength;
                fseek($handle, 0, SEEK_END);
                $nextGarbageIndex = (ftell($handle)-(2*$this->keyLength)) /$node->getPageSize();
                fwrite($handle, str_pad("", $node->getPageSize(), "\0"));
                fseek($handle, $writeSeek);
                fwrite($handle, $this->decstr($nextGarbageIndex, $this->getKeyLength()));
                
            }
            
            fseek($handle, ($this->keyLength*2)+($nextGarbageIndex*$node->getPageSize()));
        }
        
        fwrite($handle, $this->decstr($nodeIndex, $this->getKeyLength()));
        
        fseek($handle, $seekBefore, SEEK_SET);
    }
    
    protected function popGarbage()
    {
        $node = new Node();
        $node->setKeyLength($this->getKeyLength());
        $node->setForkRate($this->forkRate);
        
        $handle = $this->getStorage()->getHandle();
        $seekBefore = ftell($handle);
        
        fseek($handle, ($this->keyLength*2)+($this->getGarbageReference()*$node->getPageSize()));
        
        while (true) {
            for ($i=0; $i<($this->forkRate*3); $i++) {
                $deletedReference = fread($handle, $this->keyLength);
                if (trim($deletedReference, "\0")==="") {
                    fseek($handle, 0-($this->keyLength*2), SEEK_CUR);
                    break 2;
                }
            }
            
            $nextGarbageIndex = fread($handle, $this->keyLength); // normally this is the last-index of a page
            
            fseek($handle, ($this->keyLength*2)+($nextGarbageIndex*$node->getPageSize()));
        }
        
        $deletedReference = fread($handle, $this->keyLength);
        fseek($handle, 0-$this->keyLength, SEEK_CUR);
        fwrite($handle, str_pad("", $this->keyLength, "\0"));
        
        fseek($handle, $seekBefore, SEEK_SET);
        
        return $this->strdec($deletedReference);
    }
    
    protected function hasGarbage()
    {
        $node = new Node();
        $node->setKeyLength($this->getKeyLength());
        $node->setForkRate($this->forkRate);
        
        $handle = $this->getStorage()->getHandle();
        $seekBefore = ftell($handle);
        
        fseek($handle, ($this->keyLength*2)+($this->getGarbageReference()*$node->getPageSize()));
        $reference = fread($handle, $this->keyLength);
        
        fseek($handle, $seekBefore, SEEK_SET);
        
        return trim($reference, "\0")!=="";
    }
    
    protected function getGarbageReference()
    {
        
        $handle = $this->getStorage()->getHandle();
        $seekBefore = ftell($handle);
        
        fseek($handle, $this->keyLength, SEEK_SET);
        $reference = fread($handle, $this->keyLength);
        $reference = $this->strdec($reference);
        
        if ($reference === 0) {
            fseek($handle, 0, SEEK_END);
            $node = new Node();
            $node->setKeyLength($this->getKeyLength());
            $node->setForkRate($this->forkRate);
            
            $reference = (ftell($handle)-($this->keyLength*2)) /$node->getPageSize();
            
            fwrite($handle, str_pad("", $node->getPageSize(), "\0"));
            
            $this->setGarbageReference($reference);
        }
        
        fseek($handle, $seekBefore, SEEK_SET);
        return $reference;
    }
    
    protected function setGarbageReference($reference)
    {
        
        if (is_int($reference)) {
            $reference = $this->decstr($reference, $this->getKeyLength());
        }
        
        $handle = $this->getStorage()->getHandle();
        $seekBefore = ftell($handle);
        fseek($handle, $this->keyLength, SEEK_SET);
        
        fwrite($handle, $reference);
        
        fseek($handle, $seekBefore, SEEK_SET);
    }
    
    ### ITERATOR
    
    public function getIterator($beginValue = null, $endValue = null)
    {
        
        if (is_int($beginValue)) {
            $beginValue = $this->decstr($beginValue, $this->getKeyLength());
        }
        
        if (is_int($endValue)) {
            $endValue = $this->decstr($endValue, $this->getKeyLength());
        }
        
        if (is_null($beginValue)) {
            $beginValue = $this->getSmallestValue();
        }
        
        if (is_null($endValue)) {
            $endValue = $this->getBiggestValue();
        }
        
        $path = null;
        $nodePath     = null;
        $nodePathKeys = null;
        $index = $this;
        $value = null;
        $rowId = null;
        
        $initializeClosue = function () use (&$path, $index, $beginValue, &$endValue, &$value, &$rowId, &$nodePath, &$nodePathKeys) {
            
            if (count($path)<=0) {
                return;
            }
            
            // correctly initilize value, rowId and nodePath (TODO: could this be done more efficiently?)
            if ($endValue === $beginValue) {
                $value = $beginValue;
                $rowId = $index->getRowIdByValue($value);
                
            } elseif ($endValue > $beginValue) {
                $index->incrementIteratorPath($path, $value, $rowId, $nodePath, $nodePathKeys);
                if (count($path)<=0) {
                    return;
                }
                $index->decrementIteratorPath($path, $value, $rowId, $nodePath, $nodePathKeys);
                if ($value < $beginValue) {
                    $index->incrementIteratorPath($path, $value, $rowId, $nodePath, $nodePathKeys);
                }
            } else {
                $index->decrementIteratorPath($path, $value, $rowId, $nodePath, $nodePathKeys);
                if (count($path)<=0) {
                    return;
                }
                $index->incrementIteratorPath($path, $value, $rowId, $nodePath, $nodePathKeys);
                if ($value > $beginValue) {
                    $index->decrementIteratorPath($path, $value, $rowId, $nodePath, $nodePathKeys);
                }
            }
        };
        
        return new CustomIterator(null, [
            'rewind' => function () use (&$path, $index, $beginValue, $initializeClosue) {
                $path = $index->getIteratorPathByValue($beginValue);
                $initializeClosue();
            },
            'valid' => function () use (&$value, $endValue, $beginValue, &$path) {
                if (is_null($path)) {
                    return false;
                }
                if (strlen(trim($value, "\0"))<=0) {
                    return false;
                }
                if ($endValue > $beginValue) {
                    return $value <= $endValue;
                } else {
                    return $value >= $endValue;
                }
            },
            'current' => function () use (&$rowId) {
                return $rowId;
            },
            'key' => function () use (&$value) {
                return $value;
            },
            'next' => function () use ($index, &$path, $beginValue, $endValue, &$value, &$rowId, &$nodePath, &$nodePathKeys) {
            
                if ($endValue > $beginValue) {
                    $index->incrementIteratorPath($path, $value, $rowId, $nodePath, $nodePathKeys);
                } else {
                    $index->decrementIteratorPath($path, $value, $rowId, $nodePath, $nodePathKeys);
                }
                
            },
            'seek' => function ($seekClosure, $value) use ($index, &$path, $initializeClosue) {
                $path = $index->getIteratorPathByValue($value);
                $initializeClosue();
            }
        ]);
    }
    
    public function getSmallestValue()
    {
        
        $node = $this->getRootNode();
        
        while (trim($node->getReferenceByIndex(0), "\0")!=="") {
            $node = $this->getNode($node->getReferenceByIndex(0));
        }
        
        return $node->getValueByIndex(0);
    }
    
    public function getBiggestValue()
    {
        
        $node = $this->getRootNode();
        
        while (trim($node->getLastReference(), "\0")!=="") {
            $node = $this->getNode($node->getLastReference());
        }
        
        return $node->getValueByIndex($node->getLastWrittenIndex());
    }
    
    public function getIteratorPathByValue($value)
    {
        
        if (is_int($value)) {
            $value = $this->decstr($value, $this->getKeyLength());
        }
        
        $node  = $this->getRootNode();
        $path  = array();
        $index = 0;
        
        while (1) {
            if (!is_null($index = $node->getIndexByValue($value))) {
                $path[] = $index;
                return $path;
            }
            $index = $node->getNearestIndexByValue($value);
            if (is_null($index)) { // last-reference
                $path[] = $this->forkRate;
                $reference = $node->getLastReference();
            } else {
                $path[] = $index;
                if ($node->getValueByIndex($index) === $value) {
                    break;
                }
                $reference = $node->getReferenceByIndex($index);
            }
            if (trim($reference, "\0")==="") {
                return $path;
            }
            $node = $this->getNode($reference);
        }
    }
    
    public function incrementIteratorPath(&$path, &$value, &$rowId, &$nodePath = null, &$nodePathKeys = null)
    {
        
        if (count($path)<=0) {
            throw new Error("Invalid iterator-path given to increment!");
        }
        
        // build node-path if none is given
        if (is_null($nodePath)) {
            $node     = $this->getRootNode();
            $nodePath     = array($node);
            $nodePathKeys = array($this->getRootReference());
            
            foreach ($path as $index) {
                if ($index >= $this->forkRate) {
                    $reference = $node->getLastReference();
                } else {
                    $reference = $node->getReferenceByIndex($index);
                }
                if (trim($reference, "\0")!=="") {
                    $node = $this->getNode($reference);
                    $nodePath[]     = $node;
                    $nodePathKeys[] = $reference;
                }
            }
        }
        
        end($path);
        end($nodePath);
        end($nodePathKeys);
        
        $selectedIndexKey = key($path);
        $selectedIndex = current($path);
        prev($path);
        
        $nodeIndex = current($nodePathKeys);
        $node      = current($nodePath);
        
        if ($selectedIndex >= $node->getLastWrittenIndex()) {
            // go one level up (from leaf-nodes)
            
            array_pop($path);
            array_pop($nodePath);
            
            // end reached
            if (count($path)<=0) {
                $path         = null;
                $nodePath     = null;
                $nodePathKeys = null;
                $value        = null;
                $rowId        = null;
            } else {
                end($path);
                end($nodePath);
                end($nodePathKeys);
                $node          = current($nodePath);
                $selectedIndex = current($path);
                $value = $node->getValueByIndex($selectedIndex);
                $rowId = $node->getRowIdByIndex($selectedIndex);
            }
            
        } else {
            // go to next key in current node
            
            $path[$selectedIndexKey]++;
            $selectedIndex++;
            
            $reference = $node->getReferenceByIndex($selectedIndex);
            if (trim($reference, "\0")!=="") {
                // ge one level down (to leaf-nodes)
                    
                do {
                    $path[] = 0;
                    $node = $this->getNode($reference);
                    $nodePathKeys[] = $reference;
                    $nodePath[]     = $node;
                    $reference = $node->getReferenceByIndex(0);
                } while (trim($reference, "\0")!=="");
                
                $value = $node->getValueByIndex(0);
                $rowId = $node->getRowIdByIndex(0);
                    
            } else {
                $value = $node->getValueByIndex($selectedIndex);
                $rowId = $node->getRowIdByIndex($selectedIndex);
            }
            
        }
        
        return $path;
    }
    
    public function decrementIteratorPath(&$path, &$value, &$rowId, &$nodePath = null, &$nodePathKeys = null)
    {
        
        if (count($path)<=0) {
            throw new Error("Invalid iterator-path given to decrement!");
        }
        
        // build node-path if none is given
        if (is_null($nodePath)) {
            $node         = $this->getRootNode();
            $nodePath     = array($node);
            $nodePathKeys = array($this->getRootReference());
            
            foreach ($path as $index) {
                if ($index === $this->forkRate) {
                    $reference = $node->getLastReference();
                } else {
                    $reference = $node->getReferenceByIndex($index);
                }
                if (trim($reference, "\0")!=="") {
                    $node = $this->getNode($reference);
                    $nodePathKeys[] = $reference;
                    $nodePath[] = $node;
                }
            }
        }
        
        end($path);
        $node      = end($nodePath);
        $nodeIndex = end($nodePathKeys);
        
        $selectedIndexKey = key($path);
        $selectedIndex = current($path);
        
        if ($selectedIndex === $this->forkRate) {
            $reference = $node->getLastReference();
        } else {
            $reference = $node->getReferenceByIndex($selectedIndex);
        }
        if (trim($reference, "\0")!=="") {
            $node = $this->getNode($reference);
            $selectedIndex = $node->getLastWrittenIndex();
            $path[] = $selectedIndex;
            $nodePath[]     = $node;
            $nodePathKeys[] = $reference;
            $value = $node->getValueByIndex($selectedIndex);
            $rowId = $node->getRowIdByIndex($selectedIndex);
            
        } elseif ($selectedIndex > 0) {
            $path[$selectedIndexKey]--;
            $selectedIndex--;
            
            $value = $node->getValueByIndex($selectedIndex);
            $rowId = $node->getRowIdByIndex($selectedIndex);
            
        } else {
            array_pop($path);
            array_pop($nodePath);
            array_pop($nodePathKeys);
            
            if (count($path)<=0) {
                // end reached
                $path         = null;
                $nodePath     = null;
                $nodePathKeys = null;
                $value        = null;
                $rowId        = null;
                
            } else {
                do {
                    end($path);
                    $selectedIndexKey = key($path);
                    $path[$selectedIndexKey]--;
                    $selectedIndex = current($path);
                    
                    // if we reached the beginning of a multidim. sub-node (e.g. decrement path 1-2-0-0-0)
                    // make sure we 'slide' all the way up
                    if ($selectedIndex<0) {
                        array_pop($path);
                        array_pop($nodePath);
                        array_pop($nodePathKeys);
                        continue;
                    }
                    break;
                } while (true);
                
                $node = end($nodePath);
                end($nodePathKeys);
                
                $value = $node->getValueByIndex($selectedIndex);
                $rowId = $node->getRowIdByIndex($selectedIndex);
            }
        }
        
        return $path;
    }
    
    ### DEBUG / TEST
    
    private $isDevelopmentMode = false;
    
    public function setIsDevelopmentMode($bool)
    {
        $this->isDevelopmentMode = (bool)$bool;
    }
    
    public function getIsDevelopMentMode()
    {
        return $this->isDevelopmentMode;
    }
    
    public function dump($outputAsText = false, $doWriteOutput = true, $strapTags = false)
    {
        
        $string = "";
        
        $maximum     = pow(256, $this->getKeyLength());
        $length      = strlen($maximum);
        $lengthValue = $length;
        $countLength = strlen($this->getNodeCount());
        
        if ($outputAsText) {
            $lengthValue = $this->getKeyLength();
        }
        
        $outHandle = fopen("php://output", "a+");
        
        foreach ($this->getNodeIterator() as $nodeIndex => $node) {
            /* @var $node \Addiks\PHPSQL\Node */
        
            $nodeIndex = str_pad($nodeIndex, $countLength, " ", STR_PAD_LEFT);
            
            $line = "<i>#$nodeIndex:</i>";
            
            if ($strapTags) {
                $line = strip_tags($line);
            }
            
            $row = array($line);
        
            foreach ($node->getIterator() as $block) {
                list($reference, $value, $rowId) = $block;
                $reference = $this->strdec($reference);
                $value     = str_pad($outputAsText ?$value :$this->strdec($value), $lengthValue, " ", STR_PAD_LEFT);
                $rowId     = str_pad("<u>".$this->strdec($rowId), $length+3, " ", STR_PAD_LEFT);
                $reference = str_pad($reference === 0 ?'' :$reference, $countLength, " ", STR_PAD_LEFT);
                
                $line = ''.implode(":", ["<i>$reference</i>", "<b>$value</b>", "$rowId</u>"]).'';
                
                if ($strapTags) {
                    $line = strip_tags($line);
                }
                $row[] = $line;
            }
        
            $lastReference = $outputAsText ?$node->getLastReference() :$this->strdec($node->getLastReference());
            $lastReference = str_pad($lastReference, $countLength, " ", STR_PAD_LEFT);
            $row[] = $lastReference;
        
            $string .= implode("_t_t", $row)."\n";
            fwrite($outHandle, implode("_t_t", $row)."\n");
        }
        
        $string .= "root: {$this->getRootReference()}\n\n";
        
        if ($doWriteOutput) {
            $data = "root: <i>{$this->strdec($this->getRootReference())}</i>;&nbsp;garbage: <i>{$this->getGarbageReference()}</i>\n\n";
            if ($strapTags) {
                $data = strip_tags($data);
            }
            fputs($outHandle, $data);
        }
        
        fclose($outHandle);
        
        return str_replace("&nbsp;", " ", strip_tags($string));
    }
    
    public function selfTest()
    {
        
        $rootReference    = $this->getRootReference();
        $garbageReference = $this->decstr($this->getGarbageReference(), $this->getKeyLength());
        
        $checkReferences = array();
        
        $valuePool     = array();
        $referencePool = array($rootReference => $rootReference, $garbageReference => $garbageReference);
        
        foreach ($this->getNodeIterator() as $nodeIndex => $node) {
            /* @var $node Node */
            
            if ($nodeIndex === $this->getGarbageReference()) {
                continue;
            }
            
            $isLeaf = null;
            $valueBefore = null;
            foreach ($node->getIterator() as $blockIndex => $block) {
                list($reference, $value, $rowId) = $block;
                
                if (trim($value, "\0")==='' && trim($reference, "\0")!=='') {
                    throw new Error("Failed self-test! Found reference '{$this->strdec($reference)}' without value!");
                }
                
                if (trim($value, "\0")==='') {
                    continue;
                }
                
                $checkReferences[$nodeIndex] = $nodeIndex;
                
                if (is_null($isLeaf)) {
                    $isLeaf = trim($reference, "\0")==='';
                } elseif ($isLeaf !== (trim($reference, "\0")==='')) {
                    throw new Error("Failed self-test! Mixed node {$nodeIndex} is partly leaf and partly not!");
                }
                
                if (isset($valuePool[$value])) {
                    throw new Error("Failed self-test! Value '{$this->strdec($value)}' found twice!");
                }
                $valuePool[$value] = $value;
                
                if (trim($reference, "\0")==='') {
                    continue;
                }
                
                if ($this->strdec($reference) > $this->getNodeCount()-1) {
                    throw new Error("Failed self-test! Reference '{$this->strdec($reference)}' out of range!");
                }
                    
                if (isset($referencePool[$reference])) {
                    throw new Error("Failed self-test! Reference '{$this->strdec($reference)}' found twice!");
                }
                $referencePool[$reference] = $reference;
                
                if (is_null($valueBefore)) {
                    $valueBefore = $value;
                } elseif ($valueBefore > $value) {
                    throw new Error("Failed self-test! Values in wrong order at node {$nodeIndex}! ({$this->strdec($valueBefore)} > {$this->strdec($value)})");
                }
                
                $foreignNode = $this->getNode($reference);
                $foreignLastIndex = $foreignNode->getLastWrittenIndex();
                if ($foreignLastIndex===false) {
                    throw new Error("Failed self-test! Reference to empty node in node {$nodeIndex} for reference {$this->strdec($reference)}!");
                }
                if ($foreignNode->getValueByIndex($foreignLastIndex) > $value) {
                    throw new Error("Failed self-test! Last value in node {$this->strdec($reference)} is bigger then {$this->strdec($value)} in node {$nodeIndex}!");
                }
            }
            
            $reference = $node->getLastReference();
            
            if (trim($reference, "\0")==='') {
                continue;
                
            } elseif ($isLeaf) {
                throw new Error("Failed self-test! Mixed node {$nodeIndex} is partly leaf and partly not!");
            }
            
            if ($this->strdec($reference) > $this->getNodeCount()-1) {
                throw new Error("Failed self-test! Reference '{$this->strdec($reference)}' out of range!");
            }
            
            if (isset($referencePool[$reference])) {
                throw new Error("Failed self-test! Reference '{$this->strdec($reference)}' found twice!");
            }
            $referencePool[$reference] = $reference;
            
            $foreignNode = $this->getNode($reference);
            $value = $node->getValueByIndex($node->getLastWrittenIndex());
            if ($foreignNode->getValueByIndex(0) < $value) {
                throw new Error("Failed self-test! First value in node {$this->strdec($reference)} is smaller then {$this->strdec($value)} in node {$nodeIndex}!");
            }
        }
        
        foreach ($checkReferences as $nodeIndex) {
            if (!isset($referencePool[$this->decstr($nodeIndex, $this->getKeyLength())])) {
                throw new Error("Failed self-test! Node {$nodeIndex} is not referenced!");
            }
        }
    }
}

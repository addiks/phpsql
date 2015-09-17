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

use ErrorException;
use Addiks\PHPSQL\Entity\Page\ColumnPage;
use Addiks\PHPSQL\BinaryConverterTrait;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;

class QuickSort implements \Iterator
{
    
    use BinaryConverterTrait;
    
    /**
     *
     * @param FileResourceProxy $file
     * @param array $columnPages [[(ColumnPage)$columnPage, 'ASC'], [$columnPage2, 'DESC'], $columnPage3, ...]
     * @throws ErrorException
     */
    public function __construct(FileResourceProxy $file, array $columnPages)
    {
    
        // rebuild array to have usable keys
        $usedColumnPages = array();
        foreach ($columnPages as $columnDataset) {
            if (count($columnDataset)===2) {
                list($columnPage, $direction) = $columnDataset;
                
            } elseif (count($columnDataset)===1) {
                $columnPage = current($columnDataset);
                $direction = "ASC";
                
            } elseif ($columnDataset instanceof ColumnPage) {
                $columnPage = $columnDataset;
                $direction = "ASC";
                
            } else {
                throw new ErrorException("Invalid content in parameter \$columnPages!");
            }
            
            $direction = $direction === "ASC" ?"ASC" :"DESC";
            
            if (!$columnPage instanceof ColumnPage) {
                throw new ErrorException("Given column-page is not subclass of 'ColumnPage'!");
            }
            
            $usedColumnPages[] = [
                $columnPage,
                $direction
            ];
        }
    
        $this->file = $file;
        $this->columnPages = $usedColumnPages;
        
        if ($file->getLength() <= 0) {
            $file->setData(str_pad("", $this->getPageSize(), "\0"));
        }
    }
    
    private $file;
    
    public function getFile()
    {
        return $this->file;
    }
    
    private $columnPages;
    
    public function getColumnPages()
    {
        return $this->columnPages;
    }
    
    private $pageSize;
    
    /**
     * @return int
     */
    private function getPageSize()
    {
        if (is_null($this->pageSize)) {
            $this->pageSize = 8;
            
            foreach ($this->getColumnPages() as $index => $columnDataset) {
                list($columnPage, $direction) = $columnDataset;
                /* @var $columnPage ColumnPage */
            
                $this->pageSize += $columnPage->getCellSize();
            }
        }
        return $this->pageSize;
    }
    
    public function rewind()
    {
    
        $file = $this->getFile();
    
        $file->seek($this->getPageSize(), SEEK_SET);
    }
    
    public function valid()
    {
    
        $file = $this->getFile();
    
        $beforeIndex = $file->tell();
    
        if ($beforeIndex < $this->getPageSize()) {
            return false;
        }
        
        $rowId = $file->read(8);
    
        $file->seek($beforeIndex, SEEK_SET);
    
        return strlen($rowId)===8;
    }
    
    public function current()
    {
    
        $file = $this->getFile();
    
        $beforeIndex = $file->tell();
    
        $rowId = $file->read(8);
    
        $file->seek($beforeIndex, SEEK_SET);
    
        return $this->strdec($rowId);
    }
    
    public function key()
    {
    
        $file = $this->getFile();
    
        $index = $file->tell() / $this->getPageSize();
        
        return $index;
    }
    
    public function next()
    {
        $this->getFile()->seek($this->getPageSize(), SEEK_CUR);
    }
    
    public function count()
    {
    
        $file = $this->getFile();
        $beforeIndex = $file->tell();
    
        $file->seek(0, SEEK_END);
        $lastIndex = $file->tell() / $this->getPageSize();
    
        $file->seek($beforeIndex, SEEK_SET);
        return $lastIndex;
    }
    
    public function addRow($rowId, array $row)
    {
        
        if (!is_int(array_keys($row)[0])) {
            $index = 0;
            foreach ($row as $value) {
                $row[$index] = $value;
                $index++;
            }
        }
        
        $file = $this->getFile();
        $file->seek(0, SEEK_END);

        if (is_int($rowId)) {
            $rowId = $this->decstr($rowId);
        }
        
        $block = str_pad($rowId, 8, "\0", STR_PAD_LEFT);
        foreach ($this->getColumnPages() as $index => $columnDataset) {
            list($columnPage, $direction) = $columnDataset;
            /* @var $columnPage ColumnPage */
            
            $value = $row[$index];
            
            $value = str_pad($value, $columnPage->getCellSize(), "\0", STR_PAD_LEFT);
            
            $block .= $value;
        }
        
        $file->write($block);
    }
    
    protected function swapBlocks($blockIndexA, $blockIndexB)
    {
        
        if ($blockIndexA === $blockIndexB) {
            return;
        }
        
        $file = $this->getFile();
        
        ### READ
        
        $file->seek($blockIndexA * $this->getPageSize());
        $blockA = $file->read($this->getPageSize());
        
        $file->seek($blockIndexB * $this->getPageSize());
        $blockB = $file->read($this->getPageSize());
        
        ### WRITE
        
        $file->seek($blockIndexA * $this->getPageSize());
        $file->write($blockB);
        
        $file->seek($blockIndexB * $this->getPageSize());
        $file->write($blockA);
    }
    
    private $compareCount = 0;
    
    protected function isBlockSmallerThen($blockIndexA, $blockIndexB, $orEqual = false)
    {
        
        $this->compareCount++;
        
        $blockIndexAExport = var_export($blockIndexA, true);
        $blockIndexBExport = var_export($blockIndexB, true);
        $blockIndexAExport = str_replace(["\n", ' ', '"', "'", '.'], "", $blockIndexAExport);
        $blockIndexBExport = str_replace(["\n", ' ', '"', "'", '.'], "", $blockIndexBExport);
        
        $file = $this->getFile();
        
        ### READ A
        
        if (is_array($blockIndexA)) {
            $rowA = $blockIndexA;
            
        } else {
            $file->seek($blockIndexA * $this->getPageSize());
            
            $rawRowId = $file->read(8);
            
            $rowA = array();
            foreach ($this->getColumnPages() as $index => $columnDataset) {
                list($columnPage, $direction) = $columnDataset;
                /* @var $columnPage ColumnPage */
                
                $rowA[] = $file->read($columnPage->getCellSize());
            }
        }
        
        ### READ B
        
        if (is_array($blockIndexB)) {
            $rowB = $blockIndexB;
            
        } else {
            $file->seek($blockIndexB * $this->getPageSize());
            
            $rawRowId = $file->read(8);
            
            $rowB = array();
            foreach ($this->getColumnPages() as $index => $columnDataset) {
                list($columnPage, $direction) = $columnDataset;
                /* @var $columnPage ColumnPage */
                
                $rowB[] = $file->read($columnPage->getCellSize());
            }
        }
        
        ### COMPARE
        
        foreach ($this->getColumnPages() as $index => $columnDataset) {
            list($columnPage, $direction) = $columnDataset;
            /* @var $columnPage ColumnPage */
        
            $valueA = $rowA[$index];
            $valueB = $rowB[$index];
            
            // swap values if direction is reverse
            if ($direction === "DESC") {
                $tmp = $valueA;
                $valueA = $valueB;
                $valueB = $tmp;
            }
            
            if ($valueA < $valueB) {
                return true;
                
            } elseif ($valueA > $valueB) {
                return false;
            }
        }
        
        return $orEqual;
    }
    
    protected function getBlockRow($index)
    {
        $file = $this->getFile();
        $file->seek($index * $this->getPageSize());
        $file->read(8);
        
        $blockData = array();
        foreach ($this->getColumnPages() as $index => $columnDataset) {
            list($columnPage, $direction) = $columnDataset;
            /* @var $columnPage ColumnPage */
        
            $blockData[] = $file->read($columnPage->getCellSize());
        }
        
        return $blockData;
    }
    
    protected function partition($firstIndex, $lastIndex)
    {
        
        $i = $firstIndex;
        $j = $lastIndex -1;
        
        $pivotRow = $this->getBlockRow($lastIndex);
        
        do {
            while ($this->isBlockSmallerThen($i, $pivotRow, true) && $i < $lastIndex) {
                $i++;
            }
            
            while ($this->isBlockSmallerThen($pivotRow, $j, true) && $j > $firstIndex) {
                $j--;
            }
            
            if ($i < $j) {
                $this->swapBlocks($i, $j);
            }
            
        } while ($i < $j);
        
        if ($this->isBlockSmallerThen($pivotRow, $i)) {
            $this->swapBlocks($i, $lastIndex);
        }
        
        return $i;
    }
    
    protected function quickSort($firstIndex, $lastIndex)
    {
        
        if ($firstIndex >= $lastIndex) {
            return;
        }
        
        $pivotIndex = $this->partition($firstIndex, $lastIndex);
        
        $this->quickSort($firstIndex, $pivotIndex -1);
        $this->quickSort($pivotIndex +1, $lastIndex);
    }
    
    public function sort(&$compareCount = null)
    {
        
        $this->compareCount = 0;
        $compareCount = 0;
        
        if ($this->count() < 2) {
            return;
        }
        
        $this->quickSort(1, $this->count()-1);
        
        $compareCount = $this->compareCount;
    }
}

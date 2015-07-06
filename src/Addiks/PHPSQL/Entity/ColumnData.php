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

namespace Addiks\PHPSQL\Entity;

use Addiks\PHPSQL\Entity\Page\Column;
use Addiks\PHPSQL\Entity;
use ErrorException;
use Addiks\PHPSQL\CustomIterator;
use Addiks\PHPSQL\Entity\Storage;

class ColumnData extends Entity implements \Countable, \IteratorAggregate
{
    
    public function __construct(Storage $storage, Column $columnPage)
    {
        $this->storage = $storage;
        $this->columnSchema = $columnPage;
    }
    
    private $storage;
    
    public function getStorage()
    {
        return $this->storage;
    }
    
    private $columnSchema;
    
    /**
     *
     * @return Column
     */
    public function getColumnSchema()
    {
        return $this->columnSchema;
    }
    
    const FLAG_ISNULL = 0x01;
    
    public function getIterator()
    {
        
        /* @var $storage Storage */
        $storage = $this->getStorage();
        
        /* @var $columnSchema Column */
        $columnSchema = $this->getColumnSchema();
        
        $index = null;
        
        return CustomIterator(null, [
            'rewind'  => function () use (&$index) {
                $index = 0;
            },
            'valid'   => function () use (&$index, $storage, $columnSchema) {
                $beforeSeek = ftell($storage->getHandle());
                
                fseek($storage->getHandle(), ($index*($columnSchema->getCellSize()+1)));
                
                $flags = ord(fread($storage->getHandle(), 1));
                
                $data = fread($storage->getHandle(), $columnSchema->getCellSize());
                
                fseek($storage->getHandle(), $beforeSeek, SEEK_SET);
                return strlen($data) === $columnSchema->getCellSize();
            },
            'current' => function (&$index, $storage, $columnSchema) {
                $beforeSeek = ftell($storage->getHandle());
                
                fseek($storage->getHandle(), ($index*($columnSchema->getCellSize()+1)));
                
                $flags = ord(fread($storage->getHandle(), 1));
                
                $isNull = $flags & ColumnData::FLAG_ISNULL === ColumnData::FLAG_ISNULL;
                
                if ($isNull) {
                    fseek($storage->getHandle(), $beforeSeek, SEEK_SET);
                    return null;
                    
                } else {
                    $data = fread($storage->getHandle(), $columnSchema->getCellSize());
                    fseek($storage->getHandle(), $beforeSeek, SEEK_SET);
                    return $data;
                }
                
            },
            'key' => function () use (&$index) {
                return $index;
            },
            'next' => function () use (&$index) {
                $index++;
            },
        ]);
    }
    
    public function count()
    {
        
        /* @var $storage Storage */
        $storage = $this->getStorage();
        
        /* @var $columnSchema Column */
        $columnSchema = $this->getColumnSchema();
        
        $beforeSeek = ftell($storage->getHandle());
        
        fseek($storage->getHandle(), 0, SEEK_END);
        
        $count = floor(ftell($storage->getHandle()) / ($columnSchema->getCellSize()+1)) -1;
        
        fseek($storage->getHandle(), $beforeSeek, SEEK_SET);
        
        return $count;
    }
    
    public function getCellData($index)
    {
        
        /* @var $storage Storage */
        $storage = $this->getStorage();
        
        /* @var $columnSchema Column */
        $columnSchema = $this->getColumnSchema();
        
        $beforeSeek = ftell($storage->getHandle());
        
        fseek($storage->getHandle(), ($index*($columnSchema->getCellSize()+1)));
        
        $flags = ord(fread($storage->getHandle(), 1));
        
        $isNull = $flags & ColumnData::FLAG_ISNULL === ColumnData::FLAG_ISNULL;
        
        if ($isNull) {
            fseek($storage->getHandle(), $beforeSeek, SEEK_SET);
            return null;
        }
        
        $data = fread($storage->getHandle(), $columnSchema->getCellSize());
        
        if (strlen($data) <= 0) {
            return null;
        }
        
        if (strlen($data) !== $columnSchema->getCellSize()) {
            fseek($storage->getHandle(), $beforeSeek, SEEK_SET);
            throw new ErrorException("No or corrupted cell-data at index '{$index}'!");
        }
        
        $data = trim($data, "\0");
        
        fseek($storage->getHandle(), $beforeSeek, SEEK_SET);
        return $data;
    }
    
    public function setCellData($index, $data)
    {
        
        /* @var $storage Storage */
        $storage = $this->getStorage();
        
        /* @var $columnSchema Column */
        $columnSchema = $this->getColumnSchema();
        
        $beforeSeek = ftell($storage->getHandle());
        
        $isNull = is_null($data);
        
        $data = str_pad($data, $columnSchema->getCellSize(), "\0", STR_PAD_LEFT);
        $data = substr($data, 0, $columnSchema->getCellSize());
        
        fseek($storage->getHandle(), ($index*($columnSchema->getCellSize()+1)));
        
        $flags = 0;
        
        if ($isNull) {
            $flags = $flags ^ ColumnData::FLAG_ISNULL;
        }
        
        fwrite($storage->getHandle(), chr($flags));
        fwrite($storage->getHandle(), $data);
        
        fseek($storage->getHandle(), $beforeSeek, SEEK_SET);
    }
    
    public function addCellData($data)
    {
        
        $this->setCellData($this->count(), $data);
    }
    
    public function insertCellDataBetween($index, $cellData)
    {
        
        /* @var $storage Storage */
        $storage = $this->getStorage();
        
        /* @var $columnSchema Column */
        $columnSchema = $this->getColumnSchema();
        
        $isNull = is_null($cellData);
        
        $cellData = str_pad($cellData, $columnSchema->getCellSize(), "\0", STR_PAD_LEFT);
        $cellData = substr($cellData, 0, $columnSchema->getCellSize());
        
        $flags = 0;
        
        if ($isNull) {
            $flags = $flags ^ ColumnData::FLAG_ISNULL;
        }
        
        $data  = substr($storage->getData(), 0, $index * ($columnSchema->getCellSize()+1));
        $data .= chr($flags);
        $data .= $cellData;
        $data .= substr($storage->getData(), $index * ($columnSchema->getCellSize()+1));
        
        $storage->setData($data);
    }
    
    public function removeCell($index)
    {
        
        /* @var $storage Storage */
        $storage = $this->getStorage();
        
        /* @var $columnSchema Column */
        $columnSchema = $this->getColumnSchema();
        
        $handle = $storage->getHandle();
        
        $seekBefore = ftell($handle);
        
        fseek($handle, $index * ($columnSchema->getCellSize()+1));
        
        $flags = ColumnData::FLAG_ISNULL;
        $flags = chr($flags);
        
        fwrite($handle, $flags);
        fwrite($handle, str_pad("", $columnSchema->getCellSize(), "\0"));
        
        fseek($handle, $seekBefore, SEEK_SET);
    }
    
    public function preserveSpace($indexCount)
    {
        return;
        
        /* @var $storage Storage */
        $storage = $this->getStorage();
        
        /* @var $columnSchema Column */
        $columnSchema = $this->getColumnSchema();
        
        $beforeSeek = ftell($storage->getHandle());
        
        fseek($storage->getHandle(), ($indexCount*($columnSchema->getCellSize()+1))-1);
        fwrite($storage->getHandle(), "\0");
        
        fseek($storage->getHandle(), $beforeSeek, SEEK_SET);
    }
}

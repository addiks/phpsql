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

use Addiks\PHPSQL\Entity\Page\ColumnPage;
use Addiks\PHPSQL\Entity;
use ErrorException;
use Addiks\PHPSQL\CustomIterator;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;

class ColumnData extends Entity implements \Countable, \IteratorAggregate
{
    
    public function __construct(FileResourceProxy $file, Column $columnPage)
    {
        $this->file = $file;
        $this->columnSchema = $columnPage;
    }
    
    private $file;
    
    public function getfile()
    {
        return $this->file;
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
        
        /* @var $file file */
        $file = $this->getfile();
        
        /* @var $columnSchema Column */
        $columnSchema = $this->getColumnSchema();
        
        $index = null;
        
        return CustomIterator(null, [
            'rewind'  => function () use (&$index) {
                $index = 0;
            },
            'valid'   => function () use (&$index, $file, $columnSchema) {
                $beforeSeek = ftell($file->getHandle());
                
                fseek($file->getHandle(), ($index*($columnSchema->getCellSize()+1)));
                
                $flags = ord(fread($file->getHandle(), 1));
                
                $data = fread($file->getHandle(), $columnSchema->getCellSize());
                
                fseek($file->getHandle(), $beforeSeek, SEEK_SET);
                return strlen($data) === $columnSchema->getCellSize();
            },
            'current' => function (&$index, $file, $columnSchema) {
                $beforeSeek = ftell($file->getHandle());
                
                fseek($file->getHandle(), ($index*($columnSchema->getCellSize()+1)));
                
                $flags = ord(fread($file->getHandle(), 1));
                
                $isNull = $flags & ColumnData::FLAG_ISNULL === ColumnData::FLAG_ISNULL;
                
                if ($isNull) {
                    fseek($file->getHandle(), $beforeSeek, SEEK_SET);
                    return null;
                    
                } else {
                    $data = fread($file->getHandle(), $columnSchema->getCellSize());
                    fseek($file->getHandle(), $beforeSeek, SEEK_SET);
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
        
        /* @var $file file */
        $file = $this->getfile();
        
        /* @var $columnSchema Column */
        $columnSchema = $this->getColumnSchema();
        
        $beforeSeek = ftell($file->getHandle());
        
        fseek($file->getHandle(), 0, SEEK_END);
        
        $count = floor(ftell($file->getHandle()) / ($columnSchema->getCellSize()+1)) -1;
        
        fseek($file->getHandle(), $beforeSeek, SEEK_SET);
        
        return $count;
    }
    
    public function getCellData($index)
    {
        
        /* @var $file file */
        $file = $this->getfile();
        
        /* @var $columnSchema Column */
        $columnSchema = $this->getColumnSchema();
        
        $beforeSeek = ftell($file->getHandle());
        
        fseek($file->getHandle(), ($index*($columnSchema->getCellSize()+1)));
        
        $flags = ord(fread($file->getHandle(), 1));
        
        $isNull = $flags & ColumnData::FLAG_ISNULL === ColumnData::FLAG_ISNULL;
        
        if ($isNull) {
            fseek($file->getHandle(), $beforeSeek, SEEK_SET);
            return null;
        }
        
        $data = fread($file->getHandle(), $columnSchema->getCellSize());
        
        if (strlen($data) <= 0) {
            return null;
        }
        
        if (strlen($data) !== $columnSchema->getCellSize()) {
            fseek($file->getHandle(), $beforeSeek, SEEK_SET);
            throw new ErrorException("No or corrupted cell-data at index '{$index}'!");
        }
        
        $data = trim($data, "\0");
        
        fseek($file->getHandle(), $beforeSeek, SEEK_SET);
        return $data;
    }
    
    public function setCellData($index, $data)
    {
        
        /* @var $file file */
        $file = $this->getfile();
        
        /* @var $columnSchema Column */
        $columnSchema = $this->getColumnSchema();
        
        $beforeSeek = ftell($file->getHandle());
        
        $isNull = is_null($data);
        
        $data = str_pad($data, $columnSchema->getCellSize(), "\0", STR_PAD_LEFT);
        $data = substr($data, 0, $columnSchema->getCellSize());
        
        fseek($file->getHandle(), ($index*($columnSchema->getCellSize()+1)));
        
        $flags = 0;
        
        if ($isNull) {
            $flags = $flags ^ ColumnData::FLAG_ISNULL;
        }
        
        fwrite($file->getHandle(), chr($flags));
        fwrite($file->getHandle(), $data);
        
        fseek($file->getHandle(), $beforeSeek, SEEK_SET);
    }
    
    public function addCellData($data)
    {
        
        $this->setCellData($this->count(), $data);
    }
    
    public function insertCellDataBetween($index, $cellData)
    {
        
        /* @var $file file */
        $file = $this->getfile();
        
        /* @var $columnSchema Column */
        $columnSchema = $this->getColumnSchema();
        
        $isNull = is_null($cellData);
        
        $cellData = str_pad($cellData, $columnSchema->getCellSize(), "\0", STR_PAD_LEFT);
        $cellData = substr($cellData, 0, $columnSchema->getCellSize());
        
        $flags = 0;
        
        if ($isNull) {
            $flags = $flags ^ ColumnData::FLAG_ISNULL;
        }
        
        $data  = substr($file->getData(), 0, $index * ($columnSchema->getCellSize()+1));
        $data .= chr($flags);
        $data .= $cellData;
        $data .= substr($file->getData(), $index * ($columnSchema->getCellSize()+1));
        
        $file->setData($data);
    }
    
    public function removeCell($index)
    {
        
        /* @var $file file */
        $file = $this->getfile();
        
        /* @var $columnSchema Column */
        $columnSchema = $this->getColumnSchema();
        
        $handle = $file->getHandle();
        
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
        
        /* @var $file file */
        $file = $this->getfile();
        
        /* @var $columnSchema Column */
        $columnSchema = $this->getColumnSchema();
        
        $beforeSeek = ftell($file->getHandle());
        
        fseek($file->getHandle(), ($indexCount*($columnSchema->getCellSize()+1))-1);
        fwrite($file->getHandle(), "\0");
        
        fseek($file->getHandle(), $beforeSeek, SEEK_SET);
    }
}

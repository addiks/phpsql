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

namespace Addiks\PHPSQL\Column;

use ErrorException;
use Addiks\PHPSQL\Iterators\CustomIterator;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;
use Addiks\PHPSQL\BinaryConverterTrait;

class ColumnData implements ColumnDataInterface
{

    use BinaryConverterTrait;
    
    public function __construct(FileResourceProxy $file, ColumnSchema $columnPage)
    {
        $this->file = $file;
        $this->columnSchema = $columnPage;

        $cellSize = $columnPage->getCellSize();

        if ($cellSize <= 1) {
            $this->cellLengthSize = 1;
        } else {
            $this->cellLengthSize = ceil(log($columnPage->getCellSize(), 256));
        }
    }
    
    private $file;

    private $cellLengthSize;
    
    /**
     * @return FileResourceProxy
     */
    protected function getFile()
    {
        return $this->file;
    }
    
    private $columnSchema;
    
    /**
     *
     * @return ColumnSchema
     */
    public function getColumnSchema()
    {
        return $this->columnSchema;
    }
    
    const FLAG_ISNULL = 0x01;
    
    public function getCellData($index)
    {
        /* @var $file file */
        $file = $this->getFile();
        
        /* @var $columnSchema Column */
        $columnSchema = $this->getColumnSchema();
        
        $beforeSeek = $file->tell();
        
        $file->seek($index * $this->getPageSize());
        
        $file->seek(9, SEEK_CUR); # skip next/previous index-fields and reserved byte

        $flags = ord($file->read(1));
        
        $isNull = $flags & ColumnData::FLAG_ISNULL === ColumnData::FLAG_ISNULL;
        
        $size = $this->strdec($file->read($this->cellLengthSize));

        if ($isNull) {
            $data = null;

        } else {
            $data = $file->read($columnSchema->getCellSize());
            
            if (strlen($data) <= 0) {
                $data = null;

            } else {
                if (strlen($data) !== $columnSchema->getCellSize()) {
                    $file->seek($beforeSeek, SEEK_SET);
                    throw new ErrorException("No or corrupted cell-data at index '{$index}'!");
                } else {
                    $data = substr($data, 0, $size);
                }
            }

        }
        
        $file->seek($beforeSeek, SEEK_SET);

        return $data;
    }
    
    public function setCellData($index, $data)
    {
        /* @var $file file */
        $file = $this->getFile();
        
        /* @var $columnSchema Column */
        $columnSchema = $this->getColumnSchema();
        
        $beforeSeek = $file->tell();
        
        $isNull = is_null($data);
        
        $flags = 0;
        
        if ($isNull) {
            $flags = $flags ^ ColumnData::FLAG_ISNULL;
        }

        # make sure data (string) fit's exactly into one cell
        $data = substr($data, 0, $columnSchema->getCellSize());
        $size = strlen($data);
        $data = str_pad($data, $columnSchema->getCellSize(), "\0", STR_PAD_RIGHT);
        
        $count = $this->count();

        ### SET UP REFERENCES

        if ($index > 0) {
            $this->seek($index - 1);
            if (trim($this->current(), "\0") === '') {
                $previousIndex = $this->readCurrentPreviousIndex();
                if ($previousIndex <= -1) {
                    for ($previousIndex = $index - 1; $previousIndex >= 0; $previousIndex--) {
                        $this->seek($previousIndex);
                        if (trim($this->current(), "\0") !== '') {
                            break;
                        }
                    }
                }

                $this->seek($previousIndex + 1);
                $this->writeCurrentNextIndex($index);

                $this->seek($index - 1);
                $this->writeCurrentPreviousIndex($previousIndex);
            }
        }

        if ($index < $count-1) {
            $this->seek($index + 1);
            if (trim($this->current(), "\0") === '') {
                $nextIndex = $this->readCurrentNextIndex();
                if ($nextIndex <= -1) {
                    for ($nextIndex = $index + 1; $nextIndex < $count; $nextIndex++) {
                        $this->seek($nextIndex);
                        if (trim($this->current(), "\0") !== '') {
                            break;
                        }
                    }
                }

                $this->seek($nextIndex - 1);
                $this->writeCurrentPreviousIndex($index);

                $this->seek($index + 1);
                $this->writeCurrentNextIndex($nextIndex);
            }
        }

        ### WRITE CELL

        $this->seek($index);
        
        $file->write("\0\0\0\0"); # before-index (empty for used cell's)
        $file->write("\0\0\0\0"); # next-index (empty for used cell's)
        $file->write("\0"); # reserved byte (for future flags)
        $file->write(chr($flags));
        $file->write(str_pad($this->decstr($size), $this->cellLengthSize, "\0", STR_PAD_RIGHT));
        $file->write($data);

        $file->seek($beforeSeek, SEEK_SET);
    }
    
    public function addCellData($data)
    {
        $this->setCellData($this->count(), $data);
    }
    
    public function removeCell($index)
    {
        /* @var $file File */
        $file = $this->getFile();
        
        /* @var $columnSchema Column */
        $columnSchema = $this->getColumnSchema();
        
        $seekBefore = $file->tell();
        
        ### DELETE CELL

        $this->seek($index);
        $file->write(str_pad("", $this->getPageSize(), "\0"));

        ### SET UP REFERENCES

        $count = $this->count();

        $previousIndex = null;
        if ($index > 0) {
            $previousIndex = $index - 1;
            $this->seek($previousIndex);
            if (trim($this->current(), "\0") === '') {
                $previousIndex = $this->readCurrentPreviousIndex();
                if ($previousIndex <= -1) {
                    for ($previousIndex = $index - 1; $previousIndex >= 0; $previousIndex--) {
                        $this->seek($previousIndex);
                        if (trim($this->current(), "\0") !== '') {
                            break;
                        }
                    }
                }
            }
        }

        $nextIndex = $count;
        if ($index < $count-1) {
            $nextIndex = $index + 1;
            $this->seek($nextIndex);
            if (trim($this->current(), "\0") === '') {
                $nextIndex = $this->readCurrentNextIndex();
                if ($nextIndex <= -1) {
                    for ($nextIndex = $index + 1; $nextIndex < $count; $nextIndex++) {
                        $this->seek($nextIndex);
                        if (trim($this->current(), "\0") !== '') {
                            break;
                        }
                    }
                    if ($nextIndex >= $count) {
                        $nextIndex = -1;
                    }
                }
            }
        }

        if ($nextIndex < $count) {
            if (is_null($previousIndex)) {
                $this->seek(0);
            } else {
                $this->seek($previousIndex + 1);
            }
            $this->writeCurrentNextIndex($nextIndex);
        } else {
            $file->truncate($index * $this->getPageSize());
            $count = $index;
        }
        
        if ($previousIndex !== null) {
            if ($nextIndex >= $count) {
                $this->seek($count - 1);
            } else {
                $this->seek($nextIndex - 1);
            }
            if ($this->key() > $previousIndex) {
                $this->writeCurrentPreviousIndex($previousIndex);
            }
        }

        $file->seek($seekBefore);
    }
    
    /**
     * @deprecated ?
     */
    public function preserveSpace($lastIndex)
    {
        throw new ErrorException("Called to deprecated function 'preserveSpace'!");

        /* @var $file file */
        $file = $this->getFile();
        
        $beforeSeek = $file->tell();

        $this->seek($lastIndex + 1);
        $file->seek(-1, SEEK_CUR);
        $file->write("\0");

        $lastIndex = $this->decstr($lastIndex);
        $lastIndex = str_pad($lastIndex, 4, "\0", STR_PAD_LEFT);
        assert(strlen($lastIndex) === 4);

        $file->seek($beforeSeek + 4);
        $file->write($lastIndex);
        
        $file->seek($beforeSeek);
    }

    public function getPageSize()
    {
        /* @var $columnSchema ColumnSchema */
        $columnSchema = $this->getColumnSchema();

        return $columnSchema->getCellSize() + 10 + $this->cellLengthSize;
    }

    ### HELPERS

    private function readCurrentPreviousIndex()
    {
        assert($this->isValid);
        $previousIndex = null;

        $file = $this->getFile();
        
        $beforeSeek = $file->tell();

        $previousIndex = $file->read(4);
        $previousIndex = $this->strdec($previousIndex);
        $previousIndex--;
        
        $file->seek($beforeSeek);

        return $previousIndex;
    }

    private function writeCurrentPreviousIndex($previousIndex)
    {
        assert($this->isValid);
        assert(is_int($previousIndex));

        $file = $this->getFile();
        
        $beforeSeek = $file->tell();
    
        $previousIndex++;
        $previousIndex = $this->decstr($previousIndex);
        $previousIndex = str_pad($previousIndex, 4, "\0", STR_PAD_LEFT);
        assert(strlen($previousIndex) === 4);
        $file->write($previousIndex);

        $file->seek($beforeSeek);
    }

    private function readCurrentNextIndex()
    {
        assert($this->isValid);
        $nextIndex = null;

        $file = $this->getFile();

        $beforeSeek = $file->tell();

        $file->seek(4, SEEK_CUR);
        $nextIndex = $file->read(4);
        $nextIndex = $this->strdec($nextIndex);
        $nextIndex--;
        
        $file->seek($beforeSeek);
        return $nextIndex;
    }

    private function writeCurrentNextIndex($nextIndex)
    {
        assert($this->isValid);
        assert(is_int($nextIndex));

        $file = $this->getFile();
        
        $beforeSeek = $file->tell();
    
        $file->seek(4, SEEK_CUR);

        $nextIndex++;
        $nextIndex = $this->decstr($nextIndex);
        $nextIndex = str_pad($nextIndex, 4, "\0", STR_PAD_LEFT);
        assert(strlen($nextIndex) === 4);
        $file->write($nextIndex);

        $file->seek($beforeSeek);
    }

    private function writeCurrentCellData($cellData)
    {
        assert(is_string($cellData));
        assert($this->isValid);

        $file = $this->getFile();
        
        $beforeSeek = $file->tell();
    
        /* @var $columnSchema ColumnSchema */
        $columnSchema = $this->getColumnSchema();

        $cellData = str_pad($cellData, $columnSchema->getCellSize(), "\0", STR_PAD_LEFT);
        assert(strlen($cellData) === $columnSchema->getCellSize());

        $file->seek(10 + $this->cellLengthSize, SEEK_CUR);
        $file->write($cellData);

        $file->seek($beforeSeek);
    }

    ### ITERATOR

    protected $isValid = false;

    public function rewind()
    {
        $file = $this->getFile();
        $columnSchema = $this->getColumnSchema();

        $this->seek(0);

        $firstUsedPageIndex = $this->readCurrentNextIndex();
        if ($firstUsedPageIndex >= 0) {
            $this->seek($firstUsedPageIndex);
        }

        $beforeSeek = $file->tell();
        $file->seek(10, SEEK_CUR);
        $currentPageData = $file->read($this->getPageSize());
        $file->seek($beforeSeek);

        $this->isValid = !empty(trim($currentPageData, "\0"));
    }

    public function current()
    {
        assert($this->isValid);
        $cellData = null;

        $file = $this->getFile();
        $columnSchema = $this->getColumnSchema();

        $beforeSeek = $file->tell();

        $file->seek(10, SEEK_CUR);
        $size = $this->strdec($file->read($this->cellLengthSize));
        $cellData = $file->read($columnSchema->getCellSize());

        $file->seek($beforeSeek);

        $cellData = substr($cellData, 0, $size);

        return $cellData;
    }

    public function key()
    {
        assert($this->isValid);
        $key = null;

        $file = $this->getFile();
        $columnSchema = $this->getColumnSchema();

        $key = (int)($file->tell() / $this->getPageSize());

        return $key;
    }

    public function valid()
    {
        return $this->isValid;
    }

    public function next()
    {
        assert($this->isValid);
        $file = $this->getFile();
        $columnSchema = $this->getColumnSchema();

        $file->seek($this->getPageSize(), SEEK_CUR);

        $nextIndex = $this->readCurrentNextIndex();
        if ($nextIndex >= 0) {
            $this->seek($nextIndex);
        }

        $beforeSeek = $file->tell();
        $currentPageData = $file->read($this->getPageSize());
        $file->seek($beforeSeek);

        $this->isValid = !empty(trim($currentPageData, "\0"));
    }

    public function count()
    {
        /* @var $file file */
        $file = $this->getFile();
        
        $beforeSeek = $file->tell();
        
        $file->seek(0, SEEK_END);
        
        $count = (int)(floor($file->tell() / $this->getPageSize())  );
        
        $file->seek($beforeSeek, SEEK_SET);

        if ($count < 0) {
            $count = 0;
        }
        
        return $count;
    }
    
    public function seek($index)
    {
        /* @var $file file */
        $file = $this->getFile();

        $file->seek($index * $this->getPageSize());
        $this->isValid = true;
    }
}

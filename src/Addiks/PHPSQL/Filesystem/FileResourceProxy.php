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

namespace Addiks\PHPSQL\Filesystem;

use ErrorException;

class FileResourceProxy implements FileInterface
{

    protected $resource;
    protected $mode;
    protected $index = 0;
    protected $isOpen = true;

    public function __construct($resource, $mode = "a+")
    {

        if ($resource instanceof self) {
            $resource = $resource->getResource();
        }
        if (!is_resource($resource)) {
            throw new ErrorException("First parameter for FileResourceProxy has to be resource!");
        }

        $this->resource = $resource;
        $this->mode = $mode;

        # TODO: set index depending on mode
    }

    public function getResource()
    {
        return $this->resource;
    }

    protected function checkUsable()
    {
        if (!$this->isOpen) {
            throw new ErrorException("Filehandle is not open!");
        }
        if (!is_resource($this->resource)) {
            throw new ErrorException("File was deleted!");
        }
    }

    public function close()
    {
        $this->isOpen = false;
    }

    public function write($data)
    {
        $this->checkUsable();
        fseek($this->resource, $this->index, SEEK_SET);
        fwrite($this->resource, $data);
        $this->index = ftell($this->resource);
    }

    public function read($length)
    {
        $this->checkUsable();
        fseek($this->resource, $this->index, SEEK_SET);
        $data = "";
        if ($length > 0) {
            $data = fread($this->resource, $length);
        }
        $this->index += strlen($data);
        return $data;
    }

    public function truncate($size)
    {
        $this->checkUsable();
        ftruncate($this->resource, $size);
        $this->index = ftell($this->resource);
    }

    public function seek($offset, $seekMode = SEEK_SET)
    {
        $this->checkUsable();
        $size = $this->getSize();
        $targetOffset = [
            SEEK_SET => $offset,
            SEEK_CUR => $offset + $this->index,
            SEEK_END => $offset + $size
        ][$seekMode];
        if ($targetOffset > $size) {
            // with php://memory streams, fseek does not work when offset > size.
            fseek($this->resource, 0, SEEK_END);
            fwrite($this->resource, str_pad('', ($targetOffset - $size), "\0"));

        } else {
            fseek($this->resource, $offset, $seekMode);
        }
        $this->index = ftell($this->resource);
    }

    public function tell()
    {
        $this->checkUsable();
        return $this->index;
    }

    public function eof()
    {
        $this->checkUsable();
        fseek($this->resource, $this->index, SEEK_SET);
        return feof($this->resource);
    }

    public function lock($mode)
    {
        flock($this->resource, $mode);
    }

    public function flush()
    {
        fflush($this->resource);
    }

    public function getSize()
    {
        $seekBefore = ftell($this->resource);
        fseek($this->resource, 0, SEEK_END);
        $fileSize = ftell($this->resource);
        fseek($this->resource, $seekBefore, SEEK_SET);
        return $fileSize;
    }

    public function readLine()
    {
        $this->checkUsable();
        fseek($this->resource, $this->index, SEEK_SET);
        return fgets($this->resource);
    }

    public function getData()
    {
        $seekBefore = $this->tell();
        $this->seek(0, SEEK_END);
        $fileSize = $this->tell();
        $this->seek(0, SEEK_SET);
        $data = $this->read($fileSize);
        $this->seek($seekBefore, SEEK_SET);
        return $data;
    }

    public function setData($data)
    {
        $this->seek(0, SEEK_SET);
        $this->truncate(0);
        $this->write($data);
    }

    public function addData($data)
    {
        $seekBefore = $this->tell();
        $this->seek(0, SEEK_END);
        $this->write($data);
        $this->seek($seekBefore, SEEK_SET);
    }

    public function getLength()
    {
        $seekBefore = $this->tell();
        $this->seek(0, SEEK_END);
        $fileSize = $this->tell();
        $this->seek($seekBefore, SEEK_SET);
        return $fileSize;
    }

}
